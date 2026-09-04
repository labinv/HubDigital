<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Support;

use App\Models\User;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Proyecta el expediente normalizado a las 15 columnas del formulario
 * institucional "Datos depósito material MEPN.xlsx".
 *
 * A–J pertenecen al consultor/depositante; K–O se completan exclusivamente
 * durante la recepción y curaduría. La hoja es una vista documental: la fuente
 * de verdad permanece normalizada en PostgreSQL.
 */
final class ProyectorDatosDepositoMepn
{
    /** @return array<string, string|int|null> */
    public function proyectar(
        SolicitudDepositoEloquentModel $solicitud,
        ?User $depositante,
        ?RecepcionLoteEloquentModel $recepcion = null,
    ): array {
        return [
            'A. Nombre representante legal empresa' => $solicitud->nombre_investigador_documento ?: $depositante?->name,
            'B. Cargo o posición' => $depositante?->cargo,
            'C. Empresa o institución' => $depositante?->institucion,
            'D. No. permiso recolección' => $solicitud->nro_permiso_recoleccion,
            'E. No. permiso movilización' => $solicitud->nro_permiso_movilizacion,
            'F. Grupo animal' => $solicitud->grupo_animal,
            'G. No. individuos' => $solicitud->nro_individuos,
            'H. No. de morfoespecies' => $solicitud->nro_morfoespecies,
            'I. No. de lotes' => $solicitud->nro_lotes,
            'J. Localidad' => $this->localidad($solicitud),
            'K. No. de proceso (uso interno)' => $recepcion?->codigo_qr,
            'L. Fecha de recepción de los especímenes (uso interno)' => $recepcion?->verificado_en?->timezone('America/Guayaquil')->format('d/m/Y'),
            'M. Período (uso interno)' => $recepcion?->verificado_en?->timezone('America/Guayaquil')->format('Y'),
            'N. Observaciones (uso interno)' => $this->observaciones($recepcion?->observaciones),
            'O. Estado (uso interno)' => $recepcion?->estado ?? 'Pendiente de recepción física',
        ];
    }

    private function localidad(SolicitudDepositoEloquentModel $solicitud): ?string
    {
        $localidad = trim((string) $solicitud->localidad);
        $provincia = trim((string) $solicitud->provincia_origen);
        if ($localidad === '' && $provincia === '') {
            return null;
        }

        return $provincia !== '' ? trim($localidad.' ('.$provincia.')') : $localidad;
    }

    private function observaciones(mixed $observaciones): ?string
    {
        if (! is_array($observaciones) || $observaciones === []) {
            return null;
        }

        $texto = collect($observaciones)->map(static function (mixed $observacion): string {
            if (is_string($observacion)) {
                return $observacion;
            }
            if (is_array($observacion)) {
                return (string) ($observacion['detalle'] ?? $observacion['observacion'] ?? $observacion['texto'] ?? '');
            }

            return '';
        })->filter()->implode('; ');

        return $texto !== '' ? $texto : null;
    }
}
