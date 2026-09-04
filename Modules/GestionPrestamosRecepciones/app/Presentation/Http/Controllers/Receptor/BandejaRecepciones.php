<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Receptor;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

#[Layout('layouts.app', params: ['title' => 'Recepcion de lotes'])]
final class BandejaRecepciones extends Component
{
    public function render(UsuarioNombrePort $usuarios): View
    {
        $solicitudes = SolicitudDepositoEloquentModel::query()
            ->where('estado', EstadoSolicitudDeposito::AprobadaDocumentalmente->value)
            ->whereNotNull('codigo_qr')
            ->latest('aprobada_en')
            ->get();

        $recepciones = RecepcionLoteEloquentModel::query()
            ->whereIn('solicitud_deposito_id', $solicitudes->pluck('id'))
            ->get()
            ->keyBy('solicitud_deposito_id');

        $nombres = $usuarios->obtenerNombres($solicitudes->pluck('investigador_id')->filter()->unique()->values()->all());

        return view('gestionprestamosrecepciones::receptor.bandeja-recepciones', compact('solicitudes', 'recepciones', 'nombres'));
    }
}
