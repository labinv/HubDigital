<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Throwable;

final class RestaurarDocumentosDepositosCommand extends Command
{
    protected $signature = 'depositos:restaurar-documentos
        {--manifiesto= : Manifiesto local privado}
        {--prefijo-destino= : Prefijo remoto aislado y vacio bajo restauraciones-depositos/}
        {--directorio-destino= : Raiz local aislada y vacia; conserva las claves originales}';

    protected $description = 'Valida y restaura documentos exclusivamente hacia un prefijo aislado vacio';

    public function handle(AlmacenamientoDepositos $almacenamiento): int
    {
        $rutaManifiesto = trim((string) $this->option('manifiesto'));
        $prefijoDestino = trim((string) $this->option('prefijo-destino'), '/');
        $directorioDestino = trim((string) $this->option('directorio-destino'));
        try {
            if ($rutaManifiesto === '' || ! is_file($rutaManifiesto)) {
                throw new \RuntimeException('No se encontro el manifiesto local.');
            }
            if (($prefijoDestino === '') === ($directorioDestino === '')) {
                throw new \RuntimeException('Indique exactamente un destino remoto o local.');
            }
            $manifiesto = json_decode((string) file_get_contents($rutaManifiesto), true, 512, JSON_THROW_ON_ERROR);
            if (($manifiesto['version_formato'] ?? null) !== 1 || ($manifiesto['estado'] ?? null) !== 'COMPLETO') {
                throw new \RuntimeException('El manifiesto es incompatible o esta incompleto.');
            }
            if ($prefijoDestino !== '') {
                if (! str_starts_with($prefijoDestino.'/', 'restauraciones-depositos/')) {
                    throw new \RuntimeException('El destino remoto debe estar bajo restauraciones-depositos/.');
                }
                $pagina = $almacenamiento->listar($prefijoDestino, null, 1);
                if ($pagina['objetos'] !== []) {
                    throw new \RuntimeException('El destino remoto no esta vacio; no se sobrescribira.');
                }
            } elseif ((is_dir($directorioDestino) && scandir($directorioDestino) !== ['.', '..']) || (file_exists($directorioDestino) && ! is_dir($directorioDestino))) {
                throw new \RuntimeException('El destino local no esta vacio; no se sobrescribira.');
            }

            $restaurados = [];
            foreach ($manifiesto['objetos'] ?? [] as $origen => $objeto) {
                if (($objeto['estado'] ?? null) !== 'COPIADO' || ! is_string($objeto['destino'] ?? null) || ! is_string($objeto['sha256'] ?? null)) {
                    throw new \RuntimeException("Entrada incompleta para {$origen}.");
                }
                $contenido = $almacenamiento->obtener($objeto['destino']);
                if (! hash_equals($objeto['sha256'], hash('sha256', $contenido))) {
                    throw new \RuntimeException("El respaldo de {$origen} no conserva su SHA-256.");
                }
                if ($directorioDestino !== '') {
                    $destino = rtrim($directorioDestino, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $origen);
                    if (! is_dir(dirname($destino)) && ! mkdir(dirname($destino), 0700, true) && ! is_dir(dirname($destino))) {
                        throw new \RuntimeException('No se pudo crear el directorio aislado de restauracion.');
                    }
                    if (file_put_contents($destino, $contenido, LOCK_EX) === false || ! hash_equals($objeto['sha256'], hash_file('sha256', $destino))) {
                        throw new \RuntimeException("La restauracion local de {$origen} no conserva su SHA-256.");
                    }
                    @chmod($destino, 0600);
                } else {
                    $destino = $prefijoDestino.'/'.$origen;
                    $almacenamiento->guardarContenido($destino, $contenido, 'application/octet-stream');
                    if (! hash_equals($objeto['sha256'], $almacenamiento->sha256($destino))) {
                        throw new \RuntimeException("La restauracion de {$origen} no conserva su SHA-256.");
                    }
                }
                $restaurados[] = ['origen' => $origen, 'destino' => $destino, 'sha256' => $objeto['sha256'], 'tamano' => strlen($contenido)];
            }

            $this->output->writeln(json_encode(['estado' => 'COMPLETO', 'restaurados' => $restaurados], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Restauracion rechazada: '.$e->getMessage());

            return 4;
        }
    }
}
