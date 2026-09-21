<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/**
 * Se ejecuta en el origen, antes del corte, con R2 ya configurado. Copia cada
 * imagen historica y actualiza el registro solo despues de que R2 confirme la
 * escritura. Nunca borra el origen: su eliminacion exige una verificacion
 * posterior independiente.
 */
final class MigrarImagenesCatalogoAR2Command extends Command
{
    protected $signature = 'catalogo:migrar-imagenes-a-r2 {--dry-run : Lista sin copiar ni actualizar}';

    protected $description = 'Migra imagenes taxonomicas historicas a R2 antes del corte a Oracle.';

    public function handle(AlmacenamientoDepositos $almacenamiento): int
    {
        if ($almacenamiento->driver() !== 'r2') {
            $this->error('R2 debe estar configurado como almacenamiento obligatorio.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $filas = DB::table('divulgacion.imagenes_taxonomicas')
            ->where('disco', '!=', 'r2')
            ->orderBy('id')
            ->get(['id', 'ruta', 'disco']);
        if ($filas->isEmpty()) {
            $this->info('No hay imagenes historicas pendientes de R2.');

            return self::SUCCESS;
        }

        foreach ($filas as $fila) {
            if (! Storage::disk($fila->disco)->exists($fila->ruta)) {
                $this->error("Falta el origen de imagen {$fila->id}: {$fila->disco}/{$fila->ruta}");

                return self::FAILURE;
            }
            if ($dryRun) {
                $this->line("Pendiente: {$fila->id} {$fila->disco}/{$fila->ruta}");

                continue;
            }

            $contenido = Storage::disk($fila->disco)->get($fila->ruta);
            $mime = Storage::disk($fila->disco)->mimeType($fila->ruta) ?: 'application/octet-stream';
            if (! str_starts_with($mime, 'image/')) {
                $this->error("El origen {$fila->id} no es una imagen valida.");

                return self::FAILURE;
            }
            $almacenamiento->guardarContenido($fila->ruta, $contenido, $mime);
            DB::table('divulgacion.imagenes_taxonomicas')->where('id', $fila->id)->update(['disco' => 'r2']);
            $this->line("Migrada: {$fila->id}");
        }

        $this->info($dryRun ? 'Dry-run terminado; no se hicieron cambios.' : 'Migracion a R2 terminada; el origen se conserva para verificacion.');

        return self::SUCCESS;
    }
}
