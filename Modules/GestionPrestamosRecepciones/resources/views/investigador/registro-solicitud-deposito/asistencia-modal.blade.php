<flux:modal name="asistencia-depositos" class="w-full max-w-xl">
    <div class="space-y-3">
        <div>
            <flux:heading size="lg">Asistencia para tus documentos</flux:heading>
            <flux:text class="mt-1 text-sm text-text-secondary">Pregunta aquí sobre los documentos que debes adjuntar.</flux:text>
        </div>

        <div class="overflow-hidden rounded-xl border border-border shadow-sm">
            <div class="flex items-center gap-3 bg-blue-navy px-3 py-1 text-white">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-white/20" aria-hidden="true">
                    <flux:icon name="chat-bubble-left-right" class="size-3.5" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold leading-none">Asistente HubDigital</p>
                    <p class="text-xs text-white/80">Orientación sobre documentos de depósito</p>
                </div>
            </div>

            <div role="log" aria-live="polite" aria-label="Conversación con el asistente documental"
                 x-on:asistencia-mensaje-enviado.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                 class="h-36 space-y-2 overflow-y-auto bg-bg-main px-3 py-3 sm:h-44">
                @if($mensajesAsistencia === [])
                    <div class="mr-auto w-fit max-w-[88%] rounded-xl rounded-tl-sm bg-white px-3 py-2 text-sm text-text-primary shadow-sm">
                        <p class="mb-0.5 text-xs font-semibold text-science-blue">Asistente</p>
                        <p class="leading-5">¡Hola! Pregúntame qué documento subir o elige una pregunta rápida.</p>
                    </div>
                @endif
                @foreach($mensajesAsistencia as $indice => $mensaje)
                    <div wire:key="asistencia-{{ $indice }}"
                         @class([
                             'w-fit max-w-[88%] rounded-xl px-3 py-2 text-sm shadow-sm',
                             'ml-auto rounded-tr-sm bg-science-blue text-white' => $mensaje['rol'] === 'usuario',
                             'mr-auto rounded-tl-sm bg-white text-text-primary' => $mensaje['rol'] !== 'usuario',
                         ])>
                        <p @class([
                            'mb-0.5 text-xs font-semibold',
                            'text-white/80' => $mensaje['rol'] === 'usuario',
                            'text-science-blue' => $mensaje['rol'] !== 'usuario',
                        ])>{{ $mensaje['rol'] === 'usuario' ? 'Tú' : 'Asistente' }}</p>
                        <p class="whitespace-pre-line leading-5">{{ $mensaje['texto'] }}</p>
                    </div>
                @endforeach
                <div wire:loading wire:target="enviarPreguntaAsistencia,sugerirPreguntaAsistencia" class="mr-auto rounded-xl bg-white px-3 py-2 text-xs text-text-secondary shadow-sm" role="status">
                    Escribiendo respuesta…
                </div>
            </div>

            <div class="space-y-2 border-t border-border bg-white p-3">
                <p class="text-xs font-medium text-text-secondary">Elige una pregunta rápida:</p>
                <div class="flex flex-wrap gap-1.5" aria-label="Preguntas frecuentes">
                    <button type="button" wire:click="sugerirPreguntaAsistencia('autorizacion')" class="rounded-full border border-border px-2.5 py-1 text-xs text-science-blue hover:bg-science-blue/5">Autorización</button>
                    <button type="button" wire:click="sugerirPreguntaAsistencia('movilizacion')" class="rounded-full border border-border px-2.5 py-1 text-xs text-science-blue hover:bg-science-blue/5">Movilización</button>
                    <button type="button" wire:click="sugerirPreguntaAsistencia('expediente')" class="rounded-full border border-border px-2.5 py-1 text-xs text-science-blue hover:bg-science-blue/5">Mismo expediente</button>
                    <button type="button" wire:click="sugerirPreguntaAsistencia('ausencia')" class="rounded-full border border-border px-2.5 py-1 text-xs text-science-blue hover:bg-science-blue/5">No tengo documentos</button>
                </div>
                <form wire:submit="enviarPreguntaAsistencia" class="flex items-center gap-2">
                    <input wire:model="preguntaAsistencia" type="text" maxlength="240" required
                           aria-label="Escribe tu pregunta" placeholder="Escribe tu pregunta…"
                           class="min-w-0 flex-1 rounded-full border border-border bg-white px-4 py-2 text-sm focus:border-science-blue focus:outline-none">
                    <button type="submit" wire:loading.attr="disabled" wire:target="enviarPreguntaAsistencia"
                            aria-label="Enviar pregunta"
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-science-blue text-white hover:bg-blue-navy disabled:opacity-50">
                        <flux:icon name="paper-airplane" class="size-5" />
                    </button>
                </form>
                @error('preguntaAsistencia') <p role="alert" class="text-xs text-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end border-t border-border pt-2">
            <flux:modal.close><flux:button variant="ghost">Cerrar</flux:button></flux:modal.close>
        </div>
    </div>
</flux:modal>
