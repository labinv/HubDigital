<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Enums\RolUsuario;
use App\Models\User;
use App\Support\Administracion\CreadorUsuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Administración de usuarios')]
final class GestionUsuarios extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public string $filtroRol = '';

    public bool $mostrarFormulario = false;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $rol = RolUsuario::DEPOSITANTE->value;

    public string $cargo = '';

    public string $institucion = '';

    public function boot(): void
    {
        request()->attributes->set('administracion_sensible', true);
        $this->autorizarAdministracion();
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroRol(): void
    {
        $this->resetPage();
    }

    public function alternarFormulario(): void
    {
        $this->mostrarFormulario = ! $this->mostrarFormulario;
        $this->resetValidation();
    }

    public function crear(
        CreadorUsuario $creador,
        #[\SensitiveParameter] string $password,
        #[\SensitiveParameter] string $passwordConfirmation,
    ): void
    {
        $this->autorizarAdministracion();
        $this->email = User::normalizarEmail($this->email);
        $this->first_name = trim($this->first_name);
        $this->last_name = trim($this->last_name);

        $datos = Validator::make([
            ...$this->only([
                'first_name',
                'last_name',
                'email',
                'rol',
                'cargo',
                'institucion',
            ]),
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique(User::class, 'email_normalizado')],
            'password' => ['required', 'string', 'max:72', Password::min(12)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'rol' => ['required', Rule::enum(RolUsuario::class)],
            'cargo' => ['nullable', 'string', 'max:255'],
            'institucion' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $creador->crear($datos, RolUsuario::from($datos['rol']));

        $this->reset([
            'first_name',
            'last_name',
            'email',
            'cargo',
            'institucion',
        ]);
        $this->rol = RolUsuario::DEPOSITANTE->value;
        $this->mostrarFormulario = false;
        $this->resetPage();
        session()->flash('usuario-creado', 'Cuenta creada. La persona debe verificar su correo mediante el enlace enviado.');
    }

    public function render(): View
    {
        return view('livewire.administracion.gestion-usuarios', [
            'usuarios' => $this->usuarios(),
            'roles' => RolUsuario::cases(),
        ]);
    }

    private function autorizarAdministracion(): void
    {
        // Se vuelve a consultar la membresía en cada petición Livewire para
        // que revocar ADMIN invalide incluso un formulario que ya estaba abierto.
        abort_unless(
            auth()->check() && User::query()
                ->whereKey(auth()->id())
                ->whereNotNull('email_verified_at')
                ->whereHas('roles', fn ($roles) => $roles->where('rol', RolUsuario::ADMIN->value))
                ->exists(),
            403,
        );
    }

    /** @return LengthAwarePaginator<User> */
    private function usuarios(): LengthAwarePaginator
    {
        $termino = mb_strtolower(trim($this->busqueda));

        return User::query()
            ->with('roles')
            ->when($termino !== '', function ($consulta) use ($termino): void {
                $patron = '%'.$termino.'%';
                $consulta->where(function ($subconsulta) use ($patron): void {
                    $subconsulta
                        ->whereRaw('LOWER(first_name) LIKE ?', [$patron])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$patron])
                        ->orWhere('email_normalizado', 'like', $patron)
                        ->orWhereRaw('LOWER(COALESCE(institucion, ?)) LIKE ?', ['', $patron]);
                });
            })
            ->when($this->filtroRol !== '', function ($consulta): void {
                $rol = RolUsuario::tryFrom($this->filtroRol);
                if ($rol !== null) {
                    $consulta->whereHas('roles', fn ($roles) => $roles->where('rol', $rol->value));
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(15);
    }
}
