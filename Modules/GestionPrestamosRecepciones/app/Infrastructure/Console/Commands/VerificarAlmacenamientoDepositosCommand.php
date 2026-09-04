<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/** Comprueba escritura, lectura, integridad y borrado en el almacenamiento configurado. */
final class VerificarAlmacenamientoDepositosCommand extends Command
{
    protected $signature = 'depositos:verificar-almacenamiento {--exigir-r2 : Falla si no esta activo Cloudflare R2}';

    protected $description = 'Ejecuta una prueba real y reversible del almacenamiento privado de depositos';

    public function handle(AlmacenamientoDepositos $almacenamiento): int
    {
        try {
            $driver = $almacenamiento->driver();
            if ($this->option('exigir-r2') && $driver !== 'r2') {
                $this->error('Cloudflare R2 no esta configurado; se rechazo validar solamente el fallback local.');

                return self::FAILURE;
            }

            $contenido = json_encode([
                'prueba' => 'HubDigital EPN',
                'nonce' => (string) Str::uuid(),
            ], JSON_THROW_ON_ERROR);
            $ruta = 'pruebas-conectividad/'.Str::uuid().'.json';
            $almacenamiento->guardarContenido($ruta, $contenido, 'application/json');

            try {
                if (! $almacenamiento->existe($ruta)) {
                    throw new \RuntimeException('El objeto no existe despues de guardarlo.');
                }
                if (! hash_equals(hash('sha256', $contenido), $almacenamiento->sha256($ruta))) {
                    throw new \RuntimeException('El contenido recuperado no conserva su SHA-256.');
                }
            } finally {
                $almacenamiento->eliminar($ruta);
            }

            if ($almacenamiento->existe($ruta)) {
                throw new \RuntimeException('El objeto temporal no pudo eliminarse.');
            }

            $this->info("Almacenamiento {$driver}: escritura, lectura, integridad y borrado correctos.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Fallo la verificacion del almacenamiento: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
