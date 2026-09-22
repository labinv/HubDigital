<form wire:submit="updateProfileInformation" class="w-full space-y-5">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <flux:input wire:model="first_name" :label="__('Nombre')" type="text" required autofocus autocomplete="given-name" />
        <flux:input wire:model="last_name" :label="__('Apellido')" type="text" required autocomplete="family-name" />
    </div>

    @if ($this->esDepositante)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:input wire:model="cargo" :label="__('Cargo o posición')" type="text" required autocomplete="organization-title" placeholder="Ej. Coordinador Técnico de Proyectos" />
            <flux:input wire:model="institucion" :label="__('Institución o empresa')" type="text" required autocomplete="organization" placeholder="Ej. EcoSambito C. Ltda" />
        </div>
    @endif

    <div>
        <flux:input wire:model="email" :label="__('Correo electrónico')" type="email" required autocomplete="email" />
        @if ($this->hasUnverifiedEmail)
            <flux:text class="mt-4">
                {{ __('Tu correo electrónico no está verificado.') }}
                <flux:link class="cursor-pointer text-sm" wire:click.prevent="resendVerificationNotification">
                    {{ __('Haz clic aquí para reenviar el correo de verificación.') }}
                </flux:link>
            </flux:text>
            @if (session('status') === 'verification-link-sent')
                <flux:text class="mt-2 font-medium !text-green-600 dark:!text-green-400">
                    {{ __('Se ha enviado un nuevo enlace de verificación a tu correo electrónico.') }}
                </flux:text>
            @endif
        @endif
    </div>

    <div class="flex items-center gap-4">
        <flux:button variant="primary" type="submit" data-test="update-profile-button">
            {{ __('Guardar cambios') }}
        </flux:button>
        <x-action-message on="profile-updated">{{ __('Guardado.') }}</x-action-message>
    </div>
</form>
