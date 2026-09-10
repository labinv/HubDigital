<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\SolicitudFirmadaPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

final class EloquentSolicitudFirmadaAdapter implements SolicitudFirmadaPort
{
    public function __construct(private AlmacenamientoDepositos $almacenamiento) {}

    public function estaFirmada(string $solicitudId): bool
    {
        $documento = SolicitudDepositoEloquentModel::find($solicitudId);

        if ($documento === null
            || ! is_string($documento->solicitud_firmada_ruta)
            || $documento->solicitud_firmada_ruta === ''
            || ! is_string($documento->solicitud_firmada_sha256)
            || $documento->solicitud_firmada_sha256 === ''
            || $documento->solicitud_firmada_en === null
        ) {
            return false;
        }

        try {
            return $this->almacenamiento->existe($documento->solicitud_firmada_ruta)
                && hash_equals(
                    $documento->solicitud_firmada_sha256,
                    $this->almacenamiento->sha256($documento->solicitud_firmada_ruta),
                );
        } catch (\Throwable) {
            return false;
        }
    }
}
