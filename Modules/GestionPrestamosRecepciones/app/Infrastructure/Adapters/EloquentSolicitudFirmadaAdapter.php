<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\Log;
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
            Log::warning('La solicitud no cumple los metadatos mínimos de firma', [
                'solicitud_id' => $solicitudId,
                'existe' => $documento !== null,
                'ruta_presente' => is_string($documento?->solicitud_firmada_ruta) && $documento->solicitud_firmada_ruta !== '',
                'sha256_presente' => is_string($documento?->solicitud_firmada_sha256) && $documento->solicitud_firmada_sha256 !== '',
                'fecha_presente' => $documento?->solicitud_firmada_en !== null,
            ]);

            return false;
        }

        try {
            $existe = $this->almacenamiento->existe($documento->solicitud_firmada_ruta);
            $coincide = $existe
                && hash_equals(
                    $documento->solicitud_firmada_sha256,
                    $this->almacenamiento->sha256($documento->solicitud_firmada_ruta),
                );
            if (! $coincide) {
                Log::warning('La solicitud firmada no supera la comprobación de integridad', [
                    'solicitud_id' => $solicitudId,
                    'objeto_presente' => $existe,
                    'sha256_coincide' => false,
                ]);
            }

            return $coincide;
        } catch (\Throwable $exception) {
            Log::warning('No se pudo verificar la integridad de la solicitud firmada', [
                'solicitud_id' => $solicitudId,
                'excepcion' => $exception::class,
            ]);

            return false;
        }
    }
}
