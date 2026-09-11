<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Modules\GestionPrestamosRecepciones\Infrastructure\Operations\CatalogoDocumentalDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Throwable;

final class RespaldarDocumentosDepositosCommand extends Command
{
    protected $signature = 'depositos:respaldar-documentos
        {--id= : Identificador unico del respaldo}
        {--prefijo-destino= : Prefijo privado distinto de las claves fuente}
        {--expediente= : Limita el respaldo a un expediente}
        {--reanudar : Reutiliza copias integras ya verificadas}
        {--salida-manifiesto= : Ruta privada al manifiesto JSON}';

    protected $description = 'Copia y verifica documentos referenciados y genera un manifiesto reanudable';

    public function handle(CatalogoDocumentalDepositos $catalogo, AlmacenamientoDepositos $almacenamiento): int
    {
        $id = trim((string) ($this->option('id') ?: now()->format('Ymd-His').'-'.bin2hex(random_bytes(4))));
        $prefijo = trim((string) $this->option('prefijo-destino'), '/');
        $salida = trim((string) $this->option('salida-manifiesto'));
        if ($prefijo === '' || $salida === '' || ! str_starts_with($prefijo.'/', 'respaldos-depositos/')) {
            $this->error('Se requieren --prefijo-destino bajo respaldos-depositos/ y --salida-manifiesto.');

            return 3;
        }

        $lock = Cache::lock('depositos:respaldo-documental', 3600);
        if (! $lock->get()) {
            $this->error('Ya existe un respaldo documental incompatible en ejecucion.');

            return 4;
        }

        try {
            $manifiesto = $this->cargarOInicializar($salida, $id, $prefijo, $almacenamiento->driver());
            foreach ($catalogo->referencias($this->cadena('expediente')) as $referencia) {
                $origen = $referencia['ruta'];
                $destino = $prefijo.'/objetos/'.$origen;
                $anterior = $manifiesto['objetos'][$origen] ?? null;
                if ($this->option('reanudar') && is_array($anterior) && ($anterior['estado'] ?? null) === 'COPIADO') {
                    try {
                        if ($almacenamiento->existe($destino) && hash_equals((string) $anterior['sha256'], $almacenamiento->sha256($destino))) {
                            continue;
                        }
                    } catch (Throwable) {
                        // Se vuelve a copiar de forma segura.
                    }
                }

                try {
                    if (! $almacenamiento->existe($origen)) {
                        throw new \RuntimeException('El objeto fuente no existe.');
                    }
                    $cabecera = $almacenamiento->inspeccionar($origen);
                    $contenido = $almacenamiento->obtener($origen);
                    $sha = hash('sha256', $contenido);
                    $almacenamiento->guardarContenido($destino, $contenido, (string) ($cabecera['content_type'] ?: 'application/octet-stream'));
                    $shaDestino = $almacenamiento->sha256($destino);
                    if (! hash_equals($sha, $shaDestino)) {
                        throw new \RuntimeException('La copia no conserva SHA-256.');
                    }
                    $manifiesto['objetos'][$origen] = $referencia + ['destino' => $destino, 'tamano' => strlen($contenido), 'sha256' => $sha, 'estado' => 'COPIADO'];
                } catch (Throwable $e) {
                    $manifiesto['objetos'][$origen] = $referencia + ['destino' => $destino, 'estado' => 'FALLIDO', 'detalle' => $e->getMessage()];
                    $manifiesto['estado'] = 'INCOMPLETO';
                    $this->guardar($salida, $manifiesto);

                    return 4;
                }
                $this->guardar($salida, $manifiesto);
            }

            $manifiesto['estado'] = 'COMPLETO';
            $manifiesto['completado_en'] = now()->toIso8601String();
            $manifiesto['relaciones'] = ['usuarios.users', 'usuarios.user_roles', 'recepciones.solicitudes_deposito', 'recepciones.matrices_especies', 'recepciones.registros_especimen', 'recepciones.documentos_regulatorios', 'recepciones.recepcion_lotes', 'prestamos.historial_eventos', 'notifications', 'jobs', 'failed_jobs', 'job_batches'];
            $this->guardar($salida, $manifiesto);
            $manifiestoJson = json_encode($manifiesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $almacenamiento->guardarContenido($prefijo.'/manifiesto.json', $manifiestoJson, 'application/json');
            $this->info("Respaldo documental {$id} COMPLETO: ".count($manifiesto['objetos']).' objetos.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Fallo el respaldo documental: '.$e->getMessage());

            return 3;
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, mixed> */
    private function cargarOInicializar(string $ruta, string $id, string $prefijo, string $driver): array
    {
        if ($this->option('reanudar') && is_file($ruta)) {
            $existente = json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
            if (($existente['id'] ?? null) !== $id || ($existente['prefijo_destino'] ?? null) !== $prefijo) {
                throw new \RuntimeException('El manifiesto existente no corresponde al respaldo solicitado.');
            }

            return $existente;
        }

        return ['version_formato' => 1, 'id' => $id, 'creado_en' => now()->toIso8601String(), 'estado' => 'INCOMPLETO', 'driver' => $driver, 'prefijo_destino' => $prefijo, 'expediente' => $this->cadena('expediente'), 'objetos' => []];
    }

    /** @param array<string, mixed> $manifiesto */
    private function guardar(string $ruta, array $manifiesto): void
    {
        if (! is_dir(dirname($ruta)) && ! mkdir(dirname($ruta), 0700, true) && ! is_dir(dirname($ruta))) {
            throw new \RuntimeException('No se pudo crear el directorio privado del manifiesto.');
        }
        file_put_contents($ruta, json_encode($manifiesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX);
        @chmod($ruta, 0600);
    }

    private function cadena(string $opcion): ?string
    {
        $valor = trim((string) $this->option($opcion));

        return $valor === '' ? null : $valor;
    }
}
