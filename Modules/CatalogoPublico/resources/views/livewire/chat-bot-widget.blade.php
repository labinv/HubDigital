<div
    class="portal-chat-shell pointer-events-none fixed inset-x-0 bottom-4 z-[9999] flex justify-end px-4 sm:bottom-6 sm:px-6"
    x-data
    x-on:chat-cerrado.window="$nextTick(() => document.getElementById('chat-bot-trigger')?.focus())"
>
    <div class="pointer-events-auto flex w-full max-w-sm flex-col items-end gap-3">
        @if($abierto)
            <section
                id="chat-bot-panel"
                aria-label="Chat de consulta a la colección"
                wire:keydown.escape.window="alternar"
                class="flex h-[min(32rem,calc(100dvh-1rem))] w-full flex-col overflow-hidden rounded-lg border border-border bg-surface shadow-lg sm:h-[min(32rem,calc(100dvh-7rem))]"
            >
                <header class="flex items-center justify-between gap-2 border-b border-border bg-blue-navy px-2 py-1 text-white sm:px-4 sm:py-3">
                    <div class="flex items-center gap-2">
                        <flux:icon name="chat-bubble-left-right" class="h-5 w-5" />
                        <div class="flex flex-col">
                            <span class="text-sm font-semibold leading-tight">Consulta la colección</span>
                            <span class="text-xs text-white/70">Pregunta al chatbot</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        wire:click="alternar"
                        wire:loading.attr="disabled"
                        wire:target="alternar"
                        class="inline-flex size-11 shrink-0 items-center justify-center rounded-md text-white/80 transition-colors hover:bg-white/10 hover:text-white"
                        aria-label="Cerrar chat"
                    >
                        <flux:icon name="x-mark" class="h-5 w-5" />
                    </button>
                </header>

                <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto bg-bg-main p-2 sm:p-4">
                    @forelse($mensajes as $mensaje)
                        @if($mensaje['rol'] === 'visitante')
                            <div class="flex justify-end">
                                <div class="max-w-[85%] rounded-lg bg-science-blue px-3 py-2 text-sm text-white shadow-sm">
                                    {{ $mensaje['texto'] }}
                                </div>
                            </div>
                        @else
                            <div class="flex justify-start">
                                <div class="max-w-[85%] rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary shadow-sm">
                                    <p class="whitespace-pre-line">{{ $mensaje['texto'] }}</p>
                                    @if(! empty($mensaje['referencias']))
                                        <p class="mt-1.5 text-xs text-text-secondary">
                                            N.º de catálogo:
                                            <span class="font-mono">{{ implode(', ', $mensaje['referencias']) }}</span>
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="flex flex-1 items-center justify-center text-center text-xs text-text-secondary">
                            <p class="max-w-xs">
                                Pregunta por un género, especie, localidad o un
                                <span class="font-mono">EPN-XXXX</span>.
                            </p>
                        </div>
                    @endforelse

                    @if($procesando)
                        <div class="flex justify-start">
                            <div class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-secondary shadow-sm">
                                Buscando en la colección…
                            </div>
                        </div>
                    @endif
                </div>

                <form wire:submit="enviar" class="flex gap-2 border-t border-border bg-surface p-1 sm:p-3">
                    <flux:input
                        wire:model="pregunta"
                        placeholder="Tu pregunta…"
                        aria-label="Pregunta al bichochat"
                        required
                        wire:loading.attr="disabled"
                        wire:target="enviar"
                        size="sm"
                        class="min-h-11 flex-1"
                    />
                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:target="enviar"
                        size="sm"
                        class="min-h-11 min-w-11"
                        aria-label="Enviar pregunta"
                    >
                        <span wire:loading.remove wire:target="enviar" class="inline-flex">
                            <flux:icon name="paper-airplane" class="h-4 w-4" />
                        </span>
                        <span wire:loading wire:target="enviar" class="inline-flex">
                            <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" />
                        </span>
                    </flux:button>
                </form>
            </section>
        @endif

        <button
            id="chat-bot-trigger"
            type="button"
            wire:click="alternar"
            @class([
                'h-14 w-14 items-center justify-center rounded-full shadow-lg transition-colors',
                'flex' => ! $abierto,
                'hidden sm:flex' => $abierto,
                'bg-blue-navy text-white hover:bg-blue-navy/90' => ! $abierto,
                'bg-error text-white hover:bg-error/90' => $abierto,
            ])
            wire:loading.attr="disabled"
            wire:target="alternar"
            aria-controls="chat-bot-panel"
            aria-label="{{ $abierto ? 'Cerrar chat' : 'Abrir chat de consulta' }}"
            aria-expanded="{{ $abierto ? 'true' : 'false' }}"
        >
            <flux:icon :name="$abierto ? 'x-mark' : 'chat-bubble-left-right'" class="h-6 w-6" />
        </button>
    </div>
</div>
