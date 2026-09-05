<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecepcionLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudPrestamoModel;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\EspecimenEloquentModel;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\TaxonEloquentModel;

/** Punto de entrada de gobierno para el administrador institucional. */
#[Layout('layouts.app')]
#[Title('Centro de administración')]
final class CentroAdministracion extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
    }

    public function render(): View
    {
        $recepcionesConstatadas = [
            EstadoRecepcionLote::VerificadoFisicamente->value,
            EstadoRecepcionLote::VerificadoConObservaciones->value,
        ];

        $usuariosPorRol = User::query()
            ->with('roles')
            ->get()
            ->flatMap(fn (User $usuario) => $usuario->rolesAsignados())
            ->countBy(fn (RolUsuario $rol) => $rol->value);

        return view('livewire.administracion.centro-administracion', [
            'usuarios' => [
                'total' => User::query()->count(),
                'verificados' => User::query()->whereNotNull('email_verified_at')->count(),
                'pendientes' => User::query()->whereNull('email_verified_at')->count(),
                'porRol' => $usuariosPorRol,
            ],
            'ingresos' => [
                'porRevisar' => SolicitudDepositoEloquentModel::query()
                    ->where('estado', EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value)
                    ->count(),
                'aprobados' => SolicitudDepositoEloquentModel::query()
                    ->where('estado', EstadoSolicitudDeposito::AprobadaDocumentalmente->value)
                    ->count(),
                'actasPendientes' => RecepcionLoteEloquentModel::query()
                    ->whereIn('estado', $recepcionesConstatadas)
                    ->whereNull('acta_firmada_ruta')
                    ->count(),
                'recibidos' => RecepcionLoteEloquentModel::query()
                    ->whereIn('estado', $recepcionesConstatadas)
                    ->count(),
            ],
            'coleccion' => [
                'especimenes' => EspecimenEloquentModel::query()->count(),
                'taxones' => TaxonEloquentModel::query()->count(),
                'prestamosPorRevisar' => SolicitudPrestamoModel::query()->where('estado', 'enviada')->count(),
            ],
        ]);
    }
}
