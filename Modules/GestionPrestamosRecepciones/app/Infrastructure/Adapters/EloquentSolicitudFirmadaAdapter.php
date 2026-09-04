<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\SolicitudFirmadaPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

final class EloquentSolicitudFirmadaAdapter implements SolicitudFirmadaPort
{
    public function estaFirmada(string $solicitudId): bool
    {
        $documento = SolicitudDepositoEloquentModel::find($solicitudId);

        return $documento !== null
            && $documento->solicitud_firmada_ruta !== null
            && $documento->solicitud_firmada_sha256 !== null
            && $documento->solicitud_firmada_en !== null;
    }
}
