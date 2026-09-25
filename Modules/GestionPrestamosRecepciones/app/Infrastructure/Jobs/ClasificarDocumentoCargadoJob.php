<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Domain\Services\AnalizadorDocumentoAmbiental;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/** Identifica el tipo del documento antes de iniciar la firma electrónica. */
final class ClasificarDocumentoCargadoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        private readonly string $solicitudId,
        private readonly string $nombre,
        private readonly string $ruta,
    ) {
        if (config('hubdigital.validation_mode')) {
            $this->onQueue((string) config('hubdigital.validation_queue'));
        }
    }

    public function handle(ExtraccionDatosDocumentoPort $extractor, AnalizadorDocumentoAmbiental $analizador): void
    {
        $modelo = SolicitudDepositoEloquentModel::query()->find($this->solicitudId);
        if (($modelo?->documentos_cargados[$this->nombre] ?? null) !== $this->ruta) {
            return;
        }

        $esperado = $analizador->tipoEsperadoParaNombre($this->nombre);
        $estado = 'valido';
        if ($esperado !== null) {
            $extraido = $extractor->extraerDatos([$this->nombre => $this->ruta]);
            $detalle = $extraido->metadatosExtraccion['documentos'][$this->nombre] ?? [];
            $detectado = $detalle['analisis']['tipo_detectado'] ?? AnalizadorDocumentoAmbiental::DESCONOCIDO;
            $estado = $detectado === $esperado && ($detalle['contenido_compatible_con_casilla'] ?? false)
                ? 'valido' : 'tipo_incorrecto';
            Log::info('Clasificación automática del PDF cargado', [
                'solicitud_id' => $this->solicitudId,
                'documento' => $this->nombre,
                'tipo_esperado' => $esperado,
                'tipo_detectado' => $detectado,
                'estado' => $estado,
            ]);
        }

        $this->guardarEstado($estado);
        if ($estado === 'valido') {
            VerificarFirmaDocumentoJob::dispatch($this->solicitudId, $this->nombre, $this->ruta);
        }
    }

    public function failed(\Throwable $error): void
    {
        Log::error('Falló la clasificación automática del PDF', [
            'solicitud_id' => $this->solicitudId,
            'documento' => $this->nombre,
            'error' => $error->getMessage(),
        ]);
        $this->guardarEstado('revision_fallida');
    }

    private function guardarEstado(string $estado): void
    {
        DB::transaction(function () use ($estado): void {
            $modelo = SolicitudDepositoEloquentModel::query()->whereKey($this->solicitudId)->lockForUpdate()->first();
            if (($modelo?->documentos_cargados[$this->nombre] ?? null) !== $this->ruta) {
                return;
            }
            $validaciones = $modelo->validacion_archivos ?? [];
            $validaciones[$this->nombre] = $estado;
            $firmas = $modelo->firmas_electronicas ?? [];
            if ($estado === 'valido') {
                $firmas[$this->nombre] = 'validando';
            } else {
                unset($firmas[$this->nombre]);
            }
            $modelo->forceFill([
                'validacion_archivos' => $validaciones,
                'firmas_electronicas' => $firmas,
            ])->save();
        });
    }
}
