<div class="space-y-3">
{{-- Validación de identidad --}}
    <div class="space-y-2">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="sm" level="3">Validación de identidad</flux:heading>
            @if($resultadoIdentidad)
                <div class="flex items-center gap-2">
                    <x-gestionprestamosrecepciones::deposito-status-badge estado="{{ $resultadoIdentidad }}" />
                    {{-- Permite repetir la comprobación tras corregir el nombre en el perfil
                         o volver a cargar el formato de solicitud. --}}
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="arrow-path"
                        wire:click="resetearValidacionIdentidad"
                        wire:loading.attr="disabled"
                        wire:target="resetearValidacionIdentidad"
                    >
                        Volver a validar
                    </flux:button>
                </div>
            @endif
        </div>

        <x-gestionprestamosrecepciones::identity-card
            :nombrePerfil="auth()->user()->name"
            :nombreEnDocumento="$nombreEnDocumento ?: null"
            :resultado="$resultadoIdentidad ?: null"
        />

        @if(!$resultadoIdentidad)
            <div class="space-y-2">
                <div class="flex flex-col gap-2 sm:flex-row sm:gap-3 sm:items-end">
                    <flux:field class="flex-1 !mb-0">
                        <flux:label>Nombre tal como aparece en el formato de solicitud</flux:label>
                        <flux:input
                            wire:model="nombreEnDocumento"
                            placeholder="Ej. Juan Carlos Pérez Andrade"
                        />
                        <flux:error name="nombreEnDocumento" />
                    </flux:field>
                    <flux:button
                        variant="primary"
                        icon="shield-check"
                        wire:click="validarIdentidad"
                        wire:loading.attr="disabled"
                        wire:target="validarIdentidad"
                        class="shrink-0 bg-blue-navy hover:bg-blue-navy/90"
                    >
                        <flux:icon wire:loading wire:target="validarIdentidad" name="arrow-path" class="animate-spin size-4" />
                        Validar identidad
                    </flux:button>
                </div>
                <flux:text class="text-xs text-text-secondary">
                    Escribe el nombre exactamente como figura en el documento oficial cargado.
                </flux:text>
            </div>
        @endif

        {{-- Resultado de identidad --}}
        @if($resultadoIdentidad === 'Discrepancia (Tipográfica)')
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:heading>Discrepancia tipográfica detectada</flux:heading>
                <flux:text>{{ \App\Support\WizardCopy::text('datos.discrepancia_menor') }}</flux:text>
                <flux:button size="sm" variant="outline" wire:navigate href="{{ route('profile.edit') }}" class="mt-2">
                    Corregir nombre en perfil
                </flux:button>
            </flux:callout>
        @elseif($resultadoIdentidad === 'Discrepancia (Tercero)')
            <flux:callout variant="danger" icon="x-circle">
                <flux:heading>Discrepancia significativa detectada</flux:heading>
                <flux:text>{{ \App\Support\WizardCopy::text('datos.discrepancia_mayor') }}</flux:text>
                <div class="mt-3 flex flex-wrap gap-2">
                    <flux:button size="sm" variant="outline" wire:navigate href="{{ route('profile.edit') }}" icon="user">
                        Actualizar nombre en perfil
                    </flux:button>
                </div>
                <flux:text class="mt-2 text-xs opacity-70">{{ \App\Support\WizardCopy::text('datos.carta_delegacion') }}</flux:text>
            </flux:callout>

            <x-gestionprestamosrecepciones::dropzone
                nombre="Carta de delegación / justificación de tercero"
                propiedad="archivoCartaDelegacion"
                :requerido="true"
                :cargado="isset($documentosCargados['Carta de delegación / justificación de tercero'])"
            />
            <flux:error name="cartaDelegacion" />
        @endif

        <flux:error name="identidad" />
    </div>
</div>
