<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion\GenerarActaRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion\GenerarActaRecepcionInput;

#[Layout('layouts.app', params: ['title' => 'Acta final de recepción'])]
final class GestionActaRecepcion extends Component
{
    public string $id;

    public function mount(string $id): void
    {
        $this->id = $id;
    }

    public function generar(GenerarActaRecepcionHandler $handler): void
    {
        ($handler)(new GenerarActaRecepcionInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
        ));
        $this->dispatch('toast', message: 'Acta final generada. Ya puede revisarla y firmarla.');
    }

    public function render(ConsultarDetalleRecepcionHandler $consultar, UsuarioNombrePort $usuarios): View
    {
        $recepcion = $consultar->handle(new ConsultarDetalleRecepcionInput($this->id));
        abort_if($recepcion === null, 404);

        return view('gestionprestamosrecepciones::curador.gestion-acta-recepcion', [
            'recepcion' => $recepcion,
            'depositante' => $usuarios->obtenerNombre($recepcion->investigadorId),
            'receptor' => $recepcion->recibidoPor !== null ? $usuarios->obtenerNombre($recepcion->recibidoPor) : null,
        ]);
    }
}
