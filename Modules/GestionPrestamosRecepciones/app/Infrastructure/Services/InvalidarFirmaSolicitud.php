<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/** Invalida primero la referencia durable y retira después el objeto ya obsoleto. */
final class InvalidarFirmaSolicitud
{
    public function __construct(private AlmacenamientoDepositos $almacenamiento) {}

    public function __invoke(string $solicitudId, string $investigadorId): bool
    {
        $resultado = DB::transaction(function () use ($solicitudId, $investigadorId): ?array {
            $solicitud = SolicitudDepositoEloquentModel::query()
                ->whereKey($solicitudId)
                ->where('investigador_id', $investigadorId)
                ->lockForUpdate()
                ->first();

            if ($solicitud?->solicitud_firmada_en === null) {
                return null;
            }

            $rutaAnterior = is_string($solicitud->solicitud_firmada_ruta)
                ? $solicitud->solicitud_firmada_ruta
                : null;
            $versionAnterior = (int) $solicitud->solicitud_documento_version;

            $solicitud->forceFill([
                'solicitud_firmada_ruta' => null,
                'solicitud_firmada_sha256' => null,
                'solicitud_firmada_en' => null,
                'solicitud_firma_metadata' => [],
                'solicitud_documento_version' => $versionAnterior + 1,
            ])->save();

            return ['ruta' => $rutaAnterior, 'version' => $versionAnterior];
        });

        if ($resultado === null) {
            return false;
        }

        $rutaAnterior = $resultado['ruta'];
        if (is_string($rutaAnterior) && $rutaAnterior !== '') {
            try {
                $this->almacenamiento->eliminar($rutaAnterior);
            } catch (\Throwable $error) {
                // Un objeto sin referencia es recuperable mediante limpieza posterior;
                // una referencia durable a un objeto borrado no lo es.
                Log::warning('No se pudo retirar una solicitud firmada ya invalidada', [
                    'solicitud_id' => $solicitudId,
                    'version' => $resultado['version'],
                    'operacion' => 'invalidar_firma',
                    'error_tipo' => $error::class,
                ]);
            }
        }

        Log::info('Firma de solicitud invalidada', [
            'solicitud_id' => $solicitudId,
            'version_anterior' => $resultado['version'],
            'version_nueva' => $resultado['version'] + 1,
            'objeto_anterior_referenciado' => is_string($rutaAnterior) && $rutaAnterior !== '',
        ]);

        return true;
    }
}
