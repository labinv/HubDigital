<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Documents;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\MatrizEspeciesEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/** Genera el acta de transferencia de dominio desde el expediente aprobado. */
final class GeneradorPdfActaTransferenciaDominio
{
    public function generar(SolicitudDepositoEloquentModel $solicitud): string
    {
        $depositante = User::find($solicitud->investigador_id);
        $curador = $solicitud->curador_responsable !== null
            ? User::find($solicitud->curador_responsable)
            : null;
        $matriz = MatrizEspeciesEloquentModel::query()
            ->with('registros')
            ->where('solicitud_id', $solicitud->id)
            ->first();
        $fecha = $solicitud->aprobada_en ?? now();

        return Pdf::loadView('gestionprestamosrecepciones::pdf.acta-transferencia-dominio', [
            'solicitud' => $solicitud,
            'depositante' => $depositante,
            'curador' => $curador,
            'registros' => $matriz?->registros ?? collect(),
            'fecha' => $fecha,
        ])->setPaper('a4')->output();
    }
}
