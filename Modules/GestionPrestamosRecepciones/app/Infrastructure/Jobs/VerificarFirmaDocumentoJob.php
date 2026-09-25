<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/** Comprueba la firma de un PDF al terminar su carga, sin sacar al usuario del paso 3. */
final class VerificarFirmaDocumentoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(
        private readonly string $solicitudId,
        private readonly string $nombre,
        private readonly string $ruta,
    ) {
        if (config('hubdigital.validation_mode')) {
            $this->onQueue((string) config('hubdigital.validation_queue'));
        }
    }

    public function handle(ValidacionFirmaElectronicaPort $validador, AlmacenamientoDepositos $almacenamiento): void
    {
        $actual = SolicitudDepositoEloquentModel::query()->find($this->solicitudId);
        if (($actual?->documentos_cargados[$this->nombre] ?? null) !== $this->ruta
            || ($actual?->validacion_archivos[$this->nombre] ?? null) !== 'valido') {
            return;
        }

        $copia = $almacenamiento->copiaLocal($this->ruta);
        try {
            $estado = $validador->verificarFirma($copia->ruta())->value;
        } finally {
            $copia->limpiar();
        }

        $this->guardarEstado($estado);
    }

    public function failed(\Throwable $error): void
    {
        Log::error('Falló la comprobación automática de firma PDF', [
            'solicitud_id' => $this->solicitudId,
            'documento' => $this->nombre,
            'error' => $error->getMessage(),
        ]);
        $this->guardarEstado('verificacion_no_disponible');
    }

    private function guardarEstado(string $estado): void
    {
        DB::transaction(function () use ($estado): void {
            $modelo = SolicitudDepositoEloquentModel::query()->whereKey($this->solicitudId)->lockForUpdate()->first();
            if (($modelo?->documentos_cargados[$this->nombre] ?? null) !== $this->ruta) {
                return;
            }

            $firmas = $modelo->firmas_electronicas ?? [];
            $firmas[$this->nombre] = $estado;
            $modelo->forceFill(['firmas_electronicas' => $firmas])->save();

            DB::table('recepciones.documentos_regulatorios')
                ->where('solicitud_id', $this->solicitudId)
                ->where('ruta', $this->ruta)
                ->update(['firma_estado' => $estado, 'firma_verificada_en' => now(), 'updated_at' => now()]);
        });
    }
}
