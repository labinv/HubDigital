<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Support;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\MatrizEspeciesEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/** Genera el formulario institucional directamente desde el expediente digital. */
final class GeneradorPdfSolicitudDeposito
{
    public function generar(SolicitudDepositoEloquentModel $solicitud): string
    {
        $solicitud->refresh();
        $depositante = User::find($solicitud->investigador_id);
        $recepcion = RecepcionLoteEloquentModel::query()
            ->where('solicitud_deposito_id', $solicitud->id)
            ->first();
        $matriz = MatrizEspeciesEloquentModel::query()
            ->with('registros')
            ->where('solicitud_id', $solicitud->id)
            ->first();

        $expediente = [
            'numero' => $solicitud->numero,
            'version' => (int) ($solicitud->solicitud_documento_version ?? 1),
            'depositante' => $depositante?->only(['first_name', 'last_name', 'email', 'cargo', 'institucion']),
            'tramite' => $solicitud->tipo_tramite,
            'origen' => $solicitud->origen_recoleccion,
            'situacion_regulatoria' => $solicitud->situacion_regulatoria,
            'provincia' => $solicitud->provincia_origen,
            'localidad' => $solicitud->localidad,
            'permiso_recoleccion' => $solicitud->nro_permiso_recoleccion,
            'permiso_movilizacion' => $solicitud->nro_permiso_movilizacion,
            'grupo_animal' => $solicitud->grupo_animal,
            'individuos' => $solicitud->nro_individuos,
            'morfoespecies' => $solicitud->nro_morfoespecies,
            'lotes' => $solicitud->nro_lotes,
            'registros' => $matriz?->registros->map(fn ($registro): array => [
                'scientificName' => $registro->nombre_corregido ?: $registro->nombre_cientifico,
                'datosDwC' => $registro->datos_dwc ?? [],
            ])->values()->all() ?? [],
        ];
        $datosMepn = (new ProyectorDatosDepositoMepn)->proyectar($solicitud, $depositante, $recepcion);
        $expediente['datos_deposito_mepn'] = $datosMepn;

        return Pdf::loadView('gestionprestamosrecepciones::pdf.solicitud-deposito', [
            'solicitud' => $solicitud,
            'depositante' => $depositante,
            'registros' => $matriz?->registros ?? collect(),
            'datosMepn' => $datosMepn,
            'huellaExpediente' => hash('sha256', json_encode($expediente, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        ])->setPaper('a4')->output();
    }
}
