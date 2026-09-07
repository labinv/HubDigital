<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaTransferenciaDominioGenerada;
use Modules\GestionPrestamosRecepciones\Infrastructure\Documents\GeneradorPdfActaTransferenciaDominio;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/** Materializa en R2 el acta de transferencia anunciada por el dominio. */
final class GenerarActaTransferenciaDominioListener
{
    public function __construct(
        private readonly AlmacenamientoDepositos $almacenamiento,
        private readonly GeneradorPdfActaTransferenciaDominio $generador,
    ) {}

    public function handle(ActaTransferenciaDominioGenerada $event): void
    {
        if ($this->almacenamiento->existe($event->ruta)) {
            return;
        }

        $solicitud = SolicitudDepositoEloquentModel::findOrFail((string) $event->solicitudId);
        if ($solicitud->tipo_tramite !== 'Donación') {
            throw new \LogicException('Solo una donación puede generar un acta de transferencia de dominio.');
        }

        $pdf = $this->generador->generar($solicitud);
        $this->almacenamiento->guardarContenido($event->ruta, $pdf, 'application/pdf');

        if (! $this->almacenamiento->existe($event->ruta)) {
            throw new \RuntimeException('No se confirmó el almacenamiento del acta de transferencia de dominio.');
        }

        Log::info('Acta de transferencia de dominio materializada en almacenamiento privado.', [
            'solicitud_id' => (string) $event->solicitudId,
            'ruta' => $event->ruta,
        ]);
    }
}
