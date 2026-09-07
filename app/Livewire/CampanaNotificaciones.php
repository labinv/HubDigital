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
    public array $notificacionesPresentadas = [];

    /** @var string[] Lote enviado que queda elegible nuevamente si el cliente no confirma. */
    #[Session]
    public array $notificacionesEnTransito = [];

    /** Registra un lote enviado; si el navegador no confirma quedará disponible al renovar la sesión. */
    public function registrarLoteEnviado(array $ids): void
    {
        $ids = array_values(array_unique(array_filter($ids, 'is_string')));
        if ($ids === []) {
            return;
        }

        $propias = auth()->user()?->unreadNotifications()->whereIn('id', $ids)->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)->all() ?? [];
        $this->notificacionesEnTransito = array_values(array_unique([
            ...$this->notificacionesEnTransito,
            ...array_diff($propias, $this->notificacionesPresentadas),
        ]));
    }

    /** Confirma la presentación visual sin convertir el aviso en leído. */
    public function confirmarEntrega(array $ids): void
    {
        $ids = array_values(array_unique(array_filter($ids, 'is_string')));
        if ($ids === []) {
            return;
        }

        $propias = auth()->user()?->notifications()->whereIn('id', $ids)->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)->all() ?? [];
        $this->notificacionesPresentadas = array_values(array_unique([...$this->notificacionesPresentadas, ...$propias]));
        $this->notificacionesEnTransito = array_values(array_diff($this->notificacionesEnTransito, $propias));
    }

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
                    [...$this->notificacionesPresentadas, ...$this->notificacionesEnTransito] !== [],
                    fn ($query) => $query->whereNotIn('id', [...$this->notificacionesPresentadas, ...$this->notificacionesEnTransito]),
                )
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(10)
                ->get();

        }

        return view('livewire.campana-notificaciones', [
            'noLeidas' => $usuario ? $usuario->unreadNotifications()->count() : 0,
            'notificaciones' => $usuario ? $usuario->notifications()->latest()->limit(10)->get() : collect(),
            'notificacionesPendientes' => $notificacionesPendientes,
        ]);
    }
}
