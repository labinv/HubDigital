<div wire:poll.30s
    data-hub-notification-id="{{ $ultimaNoLeida?->id }}"
    data-hub-notification-title="HubDigital · Laboratorio de Invertebrados"
    data-hub-notification-body="{{ $ultimaNoLeida?->data['mensaje'] ?? '' }}"
    data-hub-notification-url="{{ $ultimaNoLeida?->data['url'] ?? '' }}"
    x-data="{
        open: false,
        pwaStatus: 'checking',
        pwaMessage: '',
        coords: { left: 0, top: 0 },
        toggle() {
            this.open = ! this.open;
            if (this.open) this.reposition();
        },
        reposition() {
            const t = this.$refs.trigger.getBoundingClientRect();
            const margin = 8;
            const vw = window.innerWidth;
            const pw = (this.$refs.panel && this.$refs.panel.offsetWidth) || 320;
            let left = t.left;
            if (left + pw > vw - margin) left = vw - pw - margin;
            if (left < margin) left = margin;
            this.coords = { left, top: t.bottom + margin };
        },
    }"
    x-on:resize.window="open && reposition()"
    x-on:scroll.window.capture="open && reposition()"
    x-on:keydown.escape.window="open = false"
    x-on:hub-pwa-status.window="pwaStatus = $event.detail.status; pwaMessage = $event.detail.message || ''"
    class="relative">

    <button type="button" x-ref="trigger" x-on:click="toggle()"
        class="inline-flex items-center justify-center size-9 rounded-lg text-current hover:bg-black/5 transition-colors" aria-label="Notificaciones">
        <span class="relative inline-flex">
            <flux:icon name="bell" class="size-5" />
            @if($noLeidas > 0)
                <span class="pointer-events-none absolute -top-2 -right-2 inline-flex items-center justify-center min-w-[1rem] h-4 px-1 rounded-full bg-error text-white text-[10px] leading-none font-bold tabular-nums ring-2 ring-surface">
                    {{ $noLeidas > 9 ? '9+' : $noLeidas }}
                </span>
            @endif
        </span>
    </button>

    <div x-ref="panel" x-show="open" x-cloak x-transition.origin.top.left
        x-on:click.outside="open = false"
        :style="`left: ${coords.left}px; top: ${coords.top}px`"
        class="fixed w-80 max-w-[calc(100vw-1rem)] rounded-lg border border-border bg-surface shadow-lg z-50 overflow-hidden">

        <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-border">
            <span class="text-sm font-semibold text-text-primary">Notificaciones</span>
            @if($noLeidas > 0)
                <button wire:click="marcarTodasLeidas" class="shrink-0 whitespace-nowrap text-xs font-medium text-science-blue hover:underline">
                    Marcar todas como leídas
                </button>
            @endif
        </div>

        <div class="border-b border-border bg-bg-main px-4 py-2.5" data-hub-notification-opt-in>
            <button type="button" x-show="pwaStatus !== 'enabled'" x-on:click="window.hubPwaNotifications?.enable()"
                class="flex w-full items-center justify-center gap-1.5 text-xs font-semibold text-science-blue hover:underline">
                <flux:icon name="device-phone-mobile" class="size-3.5" />
                Activar avisos en este dispositivo
            </button>
            <button type="button" x-show="pwaStatus === 'enabled'" x-on:click="window.hubPwaNotifications?.disable()"
                class="flex w-full items-center justify-center gap-1.5 text-xs font-semibold text-bio-green hover:underline">
                <flux:icon name="check-circle" class="size-3.5" />
                Avisos activos · Desactivar
            </button>
            <p x-show="pwaMessage" x-text="pwaMessage" aria-live="polite"
                class="mt-1.5 text-center text-[11px] text-text-secondary"></p>
        </div>

        <div class="max-h-96 overflow-y-auto divide-y divide-border">
            @forelse($notificaciones as $n)
                @php $leida = $n->read_at !== null; @endphp
                <button type="button" wire:click="abrir('{{ $n->id }}')"
                    class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-bg-main transition-colors {{ $leida ? '' : 'bg-science-blue/5' }}">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-bg-main border border-border">
                        <flux:icon name="{{ $n->data['icono'] ?? 'bell' }}" class="size-4 text-science-blue" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-text-primary leading-snug">{{ $n->data['mensaje'] ?? 'Notificación' }}</p>
                        <p class="text-xs text-text-secondary mt-0.5">{{ $n->created_at->diffForHumans() }}</p>
                    </div>
                    @unless($leida)
                        <span class="mt-1 size-2 shrink-0 rounded-full bg-science-blue"></span>
                    @endunless
                </button>
            @empty
                <div class="px-4 py-10 text-center">
                    <flux:icon name="bell-slash" class="size-7 mx-auto text-text-secondary/40" />
                    <p class="text-sm text-text-secondary mt-2">Sin notificaciones</p>
                </div>
            @endforelse
        </div>

        @if($notificaciones->isNotEmpty())
            <div class="border-t border-border px-4 py-2.5">
                <button type="button" wire:click="eliminarTodas"
                    wire:confirm="¿Vaciar todas las notificaciones? Esta acción no se puede deshacer."
                    class="flex w-full items-center justify-center gap-1.5 text-xs font-medium text-error hover:underline">
                    <flux:icon name="trash" class="size-3.5" />
                    Vaciar notificaciones
                </button>
            </div>
        @endif
    </div>
</div>
