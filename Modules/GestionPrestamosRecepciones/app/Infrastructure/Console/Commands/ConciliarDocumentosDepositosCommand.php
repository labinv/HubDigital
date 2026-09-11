<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\GestionPrestamosRecepciones\Infrastructure\Operations\CatalogoDocumentalDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Throwable;

final class ConciliarDocumentosDepositosCommand extends Command
{
    protected $signature = 'depositos:conciliar-documentos
        {--expediente= : Numero exacto de expediente}
        {--lote= : Codigo QR exacto de lote}
        {--prefijo= : Prefijo acotado bajo depositos/ para detectar huerfanos}
        {--tamano-lote=100 : Filas procesadas por lote}
        {--tamano-pagina-objetos=1000 : Objetos por pagina de inventario (1-1000)}
        {--salida= : Archivo JSON de salida}';

    protected $description = 'Concilia en modo solo lectura referencias documentales y almacenamiento privado';

    public function handle(CatalogoDocumentalDepositos $catalogo, AlmacenamientoDepositos $almacenamiento): int
    {
        $resultados = [];
        $referenciadas = [];
        $errorOperativo = false;

        foreach ($catalogo->referencias($this->cadena('expediente'), $this->cadena('lote'), (int) $this->option('tamano-lote')) as $referencia) {
            $ruta = $referencia['ruta'];
            $referenciadas[$ruta] = true;
            $estado = 'OK';
            $sha = null;
            $tamano = null;
            $detalle = null;
            try {
                if (! $almacenamiento->existe($ruta)) {
                    $estado = 'AUSENTE';
                } else {
                    $cabecera = $almacenamiento->inspeccionar($ruta);
                    $tamano = $cabecera['content_length'];
                    $sha = $almacenamiento->sha256($ruta);
                    if ($referencia['sha256_esperado'] !== null && ! hash_equals($referencia['sha256_esperado'], $sha)) {
                        $estado = 'ALTERADO';
                    } elseif ($referencia['version_esperada'] !== null && preg_match('/-v(\d+)(?:\.[^.]+)?$/', $ruta, $coincidencia) === 1 && (int) $coincidencia[1] !== $referencia['version_esperada']) {
                        $estado = 'VERSION_INCONSISTENTE';
                    }
                }
            } catch (Throwable $e) {
                $estado = 'NO_SE_PUDO_CONSULTAR';
                $detalle = $e->getMessage();
                $errorOperativo = true;
            }
            $resultados[] = $referencia + ['estado' => $estado, 'tamano' => $tamano, 'sha256_actual' => $sha, 'detalle' => $detalle];
        }

        $huerfanos = [];
        $prefijo = $this->cadena('prefijo');
        if ($prefijo !== null) {
            try {
                if (! str_starts_with(trim($prefijo, '/').'/', 'depositos/')) {
                    throw new \InvalidArgumentException('La deteccion de huerfanos solo admite un prefijo acotado bajo depositos/.');
                }
                $cursor = null;
                do {
                    $pagina = $almacenamiento->listar($prefijo, $cursor, max(1, min(1000, (int) $this->option('tamano-pagina-objetos'))));
                    foreach ($pagina['objetos'] as $objeto) {
                        if (! isset($referenciadas[$objeto['ruta']])) {
                            $huerfanos[] = $objeto;
                        }
                    }
                    $cursor = $pagina['cursor'];
                } while ($pagina['truncado']);
            } catch (Throwable $e) {
                $errorOperativo = true;
                $huerfanos[] = ['estado' => 'NO_SE_PUDO_CONSULTAR', 'detalle' => $e->getMessage()];
            }
        }

        $inconsistentes = count(array_filter($resultados, static fn (array $r): bool => $r['estado'] !== 'OK'));
        $salida = [
            'version_formato' => 1,
            'modo' => 'SOLO_LECTURA',
            'capturado_en' => now()->toIso8601String(),
            'driver' => $almacenamiento->driver(),
            'filtros' => ['expediente' => $this->cadena('expediente'), 'lote' => $this->cadena('lote'), 'prefijo' => $prefijo],
            'resumen' => ['referencias' => count($resultados), 'correctas' => count($resultados) - $inconsistentes, 'inconsistentes' => $inconsistentes, 'huerfanos' => count($huerfanos)],
            'resultados' => $resultados,
            'huerfanos' => $huerfanos,
            'codigos_salida' => ['0' => 'sin inconsistencias', '2' => 'inconsistencias documentales', '3' => 'error de consulta/configuracion'],
        ];
        $json = json_encode($salida, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        if ($this->cadena('salida') !== null) {
            $this->escribir($this->cadena('salida'), $json);
        }
        $this->output->write($json);

        return $errorOperativo ? 3 : (($inconsistentes > 0 || $huerfanos !== []) ? 2 : self::SUCCESS);
    }

    private function cadena(string $opcion): ?string
    {
        $valor = trim((string) $this->option($opcion));

        return $valor === '' ? null : $valor;
    }

    private function escribir(?string $ruta, string $contenido): void
    {
        if ($ruta === null) {
            return;
        }
        $directorio = dirname($ruta);
        if (! is_dir($directorio) && ! mkdir($directorio, 0700, true) && ! is_dir($directorio)) {
            throw new \RuntimeException('No se pudo crear el directorio de evidencia.');
        }
        if (file_put_contents($ruta, $contenido, LOCK_EX) === false) {
            throw new \RuntimeException('No se pudo escribir la evidencia estructurada.');
        }
        @chmod($ruta, 0600);
    }
}
