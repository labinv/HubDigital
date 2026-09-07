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
    #[Session]
    public int $historialPagina = 1;

    public function cargarMasHistorial(): void
    {
        $this->historialPagina = min(20, $this->historialPagina + 1);
    }
    /** @var string[] Identidades ya entregadas al navegador durante la sesión. */
    #[Session]
    public ?array $cursorEntregaConfirmada = null;

    /** @var string[] Lote enviado que queda elegible nuevamente si el cliente no confirma. */
    #[Session]
    public array $lotesEnTransito = [];

    /** Registra un lote enviado; si el navegador no confirma quedará disponible al renovar la sesión. */
    public function registrarLoteEnviado(array $ids): string
    {
        $this->recuperarLotesVencidos();
        $ids = array_values(array_unique(array_filter($ids, 'is_string')));
        if ($ids === []) {
            return '';
        }

        $propias = auth()->user()?->unreadNotifications()->whereIn('id', $ids)->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)->all() ?? [];
        if ($propias === []) return '';
        $lote = (string) \Illuminate\Support\Str::uuid();
        $this->lotesEnTransito[$lote] = ['ids' => $propias, 'expira_en' => now()->addMinutes(2)->timestamp];
        return $lote;
    }

    /** Confirma la presentación visual sin convertir el aviso en leído. */
    public function confirmarEntrega(string $lote, array $ids): void
    {
        $this->recuperarLotesVencidos();
        $ids = array_values(array_unique(array_filter($ids, 'is_string')));
        $pendiente = $this->lotesEnTransito[$lote] ?? null;
        if ($ids === [] || $pendiente === null || array_diff($ids, $pendiente['ids']) !== []) {
            return;
        }

        $ultima = auth()->user()?->notifications()->whereIn('id', $ids)->orderByDesc('created_at')->orderByDesc('id')->first();
        if ($ultima !== null) $this->cursorEntregaConfirmada = ['created_at' => $ultima->created_at->toIso8601String(), 'id' => (string) $ultima->id];
        unset($this->lotesEnTransito[$lote]);
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
            $this->recuperarLotesVencidos();
            $idsEnTransito = collect($this->lotesEnTransito)->pluck('ids')->flatten()->unique()->values()->all();
            $notificacionesPendientes = $usuario->unreadNotifications()
                ->when(
                    $this->cursorEntregaConfirmada !== null,
                    fn ($query) => $query->where(function ($sub): void {
                        $sub->where('created_at', '>', $this->cursorEntregaConfirmada['created_at'])
                            ->orWhere(function ($empatado): void { $empatado->where('created_at', $this->cursorEntregaConfirmada['created_at'])->where('id', '>', $this->cursorEntregaConfirmada['id']); });
                    }),
                )
                ->when($idsEnTransito !== [], fn ($query) => $query->whereNotIn('id', $idsEnTransito))
                )
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(10)
                ->get();

        }

        return view('livewire.campana-notificaciones', [
            'noLeidas' => $usuario ? $usuario->unreadNotifications()->count() : 0,
            'notificaciones' => $usuario ? $usuario->notifications()->latest()->limit($this->historialPagina * 10)->get() : collect(),
            'puedeCargarMasHistorial' => $usuario ? $usuario->notifications()->count() > ($this->historialPagina * 10) : false,
            'notificacionesPendientes' => $notificacionesPendientes,
        ]);
    }

    private function recuperarLotesVencidos(): void
    {
        $ahora = now()->timestamp;
        $this->lotesEnTransito = array_filter($this->lotesEnTransito, static fn (array $lote): bool => ($lote['expira_en'] ?? 0) > $ahora);
    }
}
