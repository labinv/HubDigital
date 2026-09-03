<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal', params: ['title' => 'Depósitos de colecciones biológicas · Laboratorio de Invertebrados EPN'])]
final class PortalDepositos extends Component
{
    public function render(): View
    {
        return view('gestionprestamosrecepciones::investigador.portal-depositos');
    }
}
