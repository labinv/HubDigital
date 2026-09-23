<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Support\WizardCopy;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Textos del asistente de depositos')]
final class ConfiguracionTextosWizard extends Component
{
    /** @var array<string, string> */
    public array $textos = [];

    public function boot(): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
    }

    public function mount(): void
    {
        $guardados = WizardCopy::all();
        foreach (config('wizard-copy', []) as $grupo => $campos) {
            foreach ($campos as $nombre => $defecto) {
                $clave = $grupo.'.'.$nombre;
                $this->textos[$grupo][$nombre] = $guardados[$clave] ?? $defecto;
            }
        }
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
        $this->validate(['textos' => ['required', 'array'], 'textos.*' => ['required', 'array'], 'textos.*.*' => ['required', 'string', 'max:1000']]);

        DB::transaction(function (): void {
            foreach (config('wizard-copy', []) as $grupo => $campos) {
                foreach ($campos as $nombre => $defecto) {
                    $clave = $grupo.'.'.$nombre;
                    DB::table('usuarios.textos_wizard')->updateOrInsert(
                        ['clave' => $clave],
                        [
                            'contenido' => trim($this->textos[$grupo][$nombre] ?? $defecto),
                            'updated_by' => auth()->id(),
                            'updated_at' => now(),
                        ],
                    );
                }
            }
        });

        WizardCopy::forget();
        session()->flash('wizard-textos', 'Textos del asistente actualizados.');
    }

    public function render(): View
    {
        return view('livewire.administracion.configuracion-textos-wizard', [
            'grupos' => config('wizard-copy', []),
        ]);
    }
}
