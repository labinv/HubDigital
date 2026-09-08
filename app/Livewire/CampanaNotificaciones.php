<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Session;
use Livewire\Component;

/** Bandeja durable de avisos del usuario autenticado. */
final class CampanaNotificaciones extends Component
{
    #[Session]
    public int $historialLimite = 10;

    /** Cursor de la última entrega visual confirmada para esta sesión y usuario. */
    #[Session]
    public ?array $cursorEntregaConfirmada = null;

    /** @var array<string, array{ids:list<string>,usuario_id:string,sesion:string,expira_en:int}> */
    #[Session]
    public array $lotesEnTransito = [];

    public function cargarMasHistorial(): void
    {
        $this->historialLimite += 10;
    }

    /** @param list<string> $ids */
    public function registrarLoteEnviado(array $ids): string
    {
        $this->recuperarLotesVencidos();
        $usuario = auth()->user();
        $ids = array_values(array_unique(array_filter($ids, 'is_string')));
        if ($usuario === null || $ids === []) {
            return '';
        }

        $propias = $usuario->unreadNotifications()->whereIn('id', array_slice($ids, 0, 3))
            ->orderBy('created_at')->orderBy('id')->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)->all();
        if ($propias === []) {
            return '';
        }

        $lote = (string) Str::uuid();
        $this->lotesEnTransito[$lote] = [
            'ids' => $propias,
            'usuario_id' => (string) $usuario->id,
            'sesion' => session()->getId(),
            'expira_en' => now()->addMinutes(2)->timestamp,
        ];

        return $lote;
    }

    /** Confirma presentación; leer y cerrar son acciones distintas. @param list<string> $ids */
    public function confirmarEntrega(string $lote, array $ids): void
    {
        $this->recuperarLotesVencidos();
        $usuario = auth()->user();
        $pendiente = $this->lotesEnTransito[$lote] ?? null;
        $ids = array_values(array_unique(array_filter($ids, 'is_string')));
        if ($usuario === null || $pendiente === null || $ids === []
            || $pendiente['usuario_id'] !== (string) $usuario->id
            || $pendiente['sesion'] !== session()->getId()
            || array_diff($ids, $pendiente['ids']) !== []) {
            return;
        }

        $ultima = $usuario->notifications()->whereIn('id', $ids)->orderByDesc('created_at')->orderByDesc('id')->first();
        if ($ultima !== null && $this->cursorEsPosterior($ultima->created_at->toIso8601String(), (string) $ultima->id)) {
            $this->cursorEntregaConfirmada = ['created_at' => $ultima->created_at->toIso8601String(), 'id' => (string) $ultima->id];
        }
        unset($this->lotesEnTransito[$lote]);
    }

    public function refrescarEntregas(): void
    {
        $this->recuperarLotesVencidos();
    }

    public function abrir(string $id): mixed
    {
        $notificacion = auth()->user()?->notifications()->find($id);
        $notificacion?->markAsRead();
        $url = $notificacion?->data['url'] ?? null;

        return $url !== null ? $this->redirect($url, navigate: true) : null;
    }

    public function marcarTodasLeidas(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();
    }

    public function eliminarTodas(): void
    {
        auth()->user()?->notifications()->delete();
    }

    public function render(): View
    {
        $usuario = auth()->user();
        $pendientes = collect();
        if ($usuario !== null) {
            $this->recuperarLotesVencidos();
            $enTransito = collect($this->lotesEnTransito)->pluck('ids')->flatten()->unique()->all();
            $pendientes = $usuario->unreadNotifications()
                ->when($this->cursorEntregaConfirmada !== null, function ($query): void {
                    $query->where(function ($sub): void {
                        $sub->where('created_at', '>', $this->cursorEntregaConfirmada['created_at'])
                            ->orWhere(function ($igual): void {
                                $igual->where('created_at', $this->cursorEntregaConfirmada['created_at'])
                                    ->where('id', '>', $this->cursorEntregaConfirmada['id']);
                            });
                    });
                })
                ->when($enTransito !== [], fn ($query) => $query->whereNotIn('id', $enTransito))
                ->orderBy('created_at')->orderBy('id')->limit(3)->get();
        }

        $historial = $usuario ? $usuario->notifications()->latest()->limit($this->historialLimite + 1)->get() : collect();
        $puedeCargarMas = $historial->count() > $this->historialLimite;
        if ($puedeCargarMas) {
            $historial->pop();
        }

        return view('livewire.campana-notificaciones', [
            'noLeidas' => $usuario ? $usuario->unreadNotifications()->count() : 0,
            'notificaciones' => $historial,
            'puedeCargarMasHistorial' => $puedeCargarMas,
            'notificacionesPendientes' => $pendientes,
        ]);
    }

    private function recuperarLotesVencidos(): void
    {
        $ahora = now()->timestamp;
        $usuarioId = (string) auth()->id();
        $sesion = session()->getId();
        $this->lotesEnTransito = array_filter($this->lotesEnTransito, static fn (array $lote): bool =>
            ($lote['expira_en'] ?? 0) > $ahora
            && ($lote['usuario_id'] ?? null) === $usuarioId
            && ($lote['sesion'] ?? null) === $sesion,
        );
    }

    private function cursorEsPosterior(string $fecha, string $id): bool
    {
        if ($this->cursorEntregaConfirmada === null) {
            return true;
        }

        return $fecha > $this->cursorEntregaConfirmada['created_at']
            || ($fecha === $this->cursorEntregaConfirmada['created_at'] && $id > $this->cursorEntregaConfirmada['id']);
    }
}
