<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Support\ConfiguracionExpediente as PrefijoExpediente;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Siglas de expedientes de depósitos')]
final class ConfiguracionExpediente extends Component
{
    public string $prefijo = PrefijoExpediente::PREFIJO_PREDETERMINADO;

    public function boot(): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
    }

    public function mount(): void
    {
        $this->prefijo = PrefijoExpediente::prefijo();
    }

    public function guardar(): void
    {
        $this->prefijo = strtoupper(trim($this->prefijo));
        $this->validate([
            'prefijo' => ['required', 'regex:/^[A-Z]{2,10}(?:-[A-Z]{2,10}){0,2}-DEP$/', 'max:40'],
        ], [
            'prefijo.regex' => 'Usa siglas mayúsculas separadas por guiones y termina en -DEP (por ejemplo, MEPN-INV-DEP).',
        ]);

        DB::table('usuarios.configuracion_expediente')->updateOrInsert(
            ['id' => 1],
            ['prefijo' => $this->prefijo, 'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()],
        );
        PrefijoExpediente::olvidar();
        session()->flash('prefijo-guardado', 'Las siglas se aplicarán a los nuevos expedientes.');
    }

    public function render(): View
    {
        return view('livewire.administracion.configuracion-expediente');
    }
}
