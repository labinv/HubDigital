<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\Attributes\Session;

/**
 * Campana de notificaciones del portal. Muestra el conteo de no leídas y las últimas
 * notificaciones del usuario autenticado (notificaciones de base de datos de Laravel),
 * permitiendo marcarlas como leídas y navegar al recurso relacionado.
 */
final class CampanaNotificaciones extends Component
{
    /** @var string[] Identidades ya entregadas al navegador durante la sesión. */
    #[Session]
    public array $notificacionesEntregadas = [];

    /**
     * Marca una notificación como leída y navega a su recurso.
     */
    public function abrir(string $id): mixed
    {
        $notificacion = auth()->user()?->notifications()->find($id);
        $notificacion?->markAsRead();

        $url = $notificacion?->data['url'] ?? null;

        return $url !== null ? $this->redirect($url, navigate: true) : null;
    }

    /**
     * Marca todas las notificaciones del usuario como leídas.
     */
    public function marcarTodasLeidas(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();
    }

    /**
     * Elimina (vacía) todas las notificaciones del usuario.
     */
    public function eliminarTodas(): void
    {
        auth()->user()?->notifications()->delete();
    }

    public function render(): View
    {
        $usuario = auth()->user();
        $notificacionesPendientes = collect();

        if ($usuario !== null) {
            $notificacionesPendientes = $usuario->unreadNotifications()
                ->when(
                    $this->notificacionesEntregadas !== [],
                    fn ($query) => $query->whereNotIn('id', $this->notificacionesEntregadas),
                )
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(10)
                ->get();

            if ($notificacionesPendientes->isNotEmpty()) {
                $this->notificacionesEntregadas = array_slice(array_values(array_unique([
                    ...$this->notificacionesEntregadas,
                    ...$notificacionesPendientes->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all(),
                ])), -80);
            }
        }

        return view('livewire.campana-notificaciones', [
            'noLeidas' => $usuario ? $usuario->unreadNotifications()->count() : 0,
            'notificaciones' => $usuario ? $usuario->notifications()->latest()->limit(10)->get() : collect(),
            'notificacionesPendientes' => $notificacionesPendientes,
        ]);
    }
}
