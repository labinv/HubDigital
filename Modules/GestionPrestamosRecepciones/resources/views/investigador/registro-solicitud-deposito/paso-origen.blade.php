<div
    class="space-y-6"
    x-data="{
        origen: @js($origenRecoleccion),
        situacion: @js($situacionRegulatoria),
        provincia: @js($provincia),
        seleccionarOrigen(val) {
            this.origen = val;
            if (val === 'Exterior (Extranjero)') {
                this.situacion = 'Proviene de colección foránea';
            } else if (this.situacion === 'Proviene de colección foránea') {
                this.situacion = '';
            }
            $dispatch('radio-card-select', { grupo: 'origenRecoleccion', valor: val });
            $wire.set('origenRecoleccion', val);
        },
        seleccionarSituacion(val) {
            this.situacion = val;
            $dispatch('radio-card-select', { grupo: 'situacionRegulatoria', valor: val });
            $wire.set('situacionRegulatoria', val);
        },
        seleccionarZona(titulo, wireVal) {
            this.provincia = wireVal;
            $dispatch('radio-card-select', { grupo: 'provincia', valor: titulo });
            $wire.set('provincia', wireVal);
        },
    }"
>

    <div class="border-b border-blue-navy/10 pb-5">
        <flux:heading size="lg" level="2" class="font-display tracking-tight text-blue-navy">{{ \App\Support\WizardCopy::text('origen.titulo') }}</flux:heading>
        <flux:text class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">{{ \App\Support\WizardCopy::text('origen.intro') }}</flux:text>
    </div>

    <div class="space-y-3">
        <flux:label>Procedencia geográfica <span class="text-error">*</span></flux:label>
        <flux:error name="origenRecoleccion" />

        <div class="grid gap-3 sm:grid-cols-2">
            <x-gestionprestamosrecepciones::radio-card
                :activo="$origenRecoleccion === 'Nacional (Ecuador)'"
                titulo="Nacional (Ecuador)"
                grupo="origenRecoleccion"
                descripcion="{{ \App\Support\WizardCopy::text('origen.nacional') }}"
                role="radio"
                x-bind:aria-checked="active"
                tabindex="0"
                x-on:click="seleccionarOrigen('Nacional (Ecuador)')"
                x-on:keydown.enter.prevent="seleccionarOrigen('Nacional (Ecuador)')"
                x-on:keydown.space.prevent="seleccionarOrigen('Nacional (Ecuador)')"
            >
                <x-slot:icono>
                    <flux:icon name="flag" class="size-5 text-science-blue" />
                </x-slot:icono>
            </x-gestionprestamosrecepciones::radio-card>

            <x-gestionprestamosrecepciones::radio-card
                :activo="$origenRecoleccion === 'Exterior (Extranjero)'"
                titulo="Exterior (Extranjero)"
                grupo="origenRecoleccion"
                descripcion="{{ \App\Support\WizardCopy::text('origen.exterior') }}"
                role="radio"
                x-bind:aria-checked="active"
                tabindex="0"
                x-on:click="seleccionarOrigen('Exterior (Extranjero)')"
                x-on:keydown.enter.prevent="seleccionarOrigen('Exterior (Extranjero)')"
                x-on:keydown.space.prevent="seleccionarOrigen('Exterior (Extranjero)')"
            >
                <x-slot:icono>
                    <flux:icon name="globe-alt" class="size-5 text-science-blue" />
                </x-slot:icono>
            </x-gestionprestamosrecepciones::radio-card>
        </div>
    </div>

    <div x-show="origen === 'Nacional (Ecuador)'" x-cloak class="space-y-6">

        <div class="space-y-3">
            <flux:label>Situación regulatoria <span class="text-error">*</span></flux:label>
            <flux:error name="situacionRegulatoria" />

            <div class="grid gap-3 sm:grid-cols-2">
                <x-gestionprestamosrecepciones::radio-card
                    :activo="$situacionRegulatoria === 'Posee permisos del MAE'"
                    titulo="Posee permisos del MAE"
                    grupo="situacionRegulatoria"
                    descripcion="{{ \App\Support\WizardCopy::text('origen.con_permisos') }}"
                    role="radio"
                    x-bind:aria-checked="active"
                    tabindex="0"
                    x-on:click="seleccionarSituacion('Posee permisos del MAE')"
                    x-on:keydown.enter.prevent="seleccionarSituacion('Posee permisos del MAE')"
                    x-on:keydown.space.prevent="seleccionarSituacion('Posee permisos del MAE')"
                >
                    <x-slot:icono>
                        <flux:icon name="document-check" class="size-5 text-bio-green" />
                    </x-slot:icono>
                </x-gestionprestamosrecepciones::radio-card>

                <x-gestionprestamosrecepciones::radio-card
                    :activo="$situacionRegulatoria === 'Sin permisos del MAE'"
                    titulo="Sin permisos del MAE"
                    grupo="situacionRegulatoria"
                    descripcion="{{ \App\Support\WizardCopy::text('origen.sin_permisos') }}"
                    role="radio"
                    x-bind:aria-checked="active"
                    tabindex="0"
                    x-on:click="seleccionarSituacion('Sin permisos del MAE')"
                    x-on:keydown.enter.prevent="seleccionarSituacion('Sin permisos del MAE')"
                    x-on:keydown.space.prevent="seleccionarSituacion('Sin permisos del MAE')"
                >
                    <x-slot:icono>
                        <flux:icon name="exclamation-triangle" class="size-5 text-warning" />
                    </x-slot:icono>
                </x-gestionprestamosrecepciones::radio-card>
            </div>
        </div>

        <div class="space-y-3">
            <flux:label>Zona de recolección <span class="text-error">*</span></flux:label>
            <flux:error name="provincia" />

            <div class="grid gap-3 sm:grid-cols-2">
                <x-gestionprestamosrecepciones::radio-card
                    :activo="$provincia === 'Pichincha'"
                    titulo="Dentro de Pichincha"
                    grupo="provincia"
                    descripcion="{{ \App\Support\WizardCopy::text('origen.pichincha') }}"
                    role="radio"
                    x-bind:aria-checked="active"
                    tabindex="0"
                    x-on:click="seleccionarZona('Dentro de Pichincha', 'Pichincha')"
                    x-on:keydown.enter.prevent="seleccionarZona('Dentro de Pichincha', 'Pichincha')"
                    x-on:keydown.space.prevent="seleccionarZona('Dentro de Pichincha', 'Pichincha')"
                >
                    <x-slot:icono>
                        <flux:icon name="map-pin" class="size-5 text-bio-green" />
                    </x-slot:icono>
                </x-gestionprestamosrecepciones::radio-card>

                <x-gestionprestamosrecepciones::radio-card
                    :activo="$provincia === 'Fuera de Pichincha'"
                    titulo="Fuera de Pichincha"
                    grupo="provincia"
                    descripcion="{{ \App\Support\WizardCopy::text('origen.otra_provincia') }}"
                    role="radio"
                    x-bind:aria-checked="active"
                    tabindex="0"
                    x-on:click="seleccionarZona('Fuera de Pichincha', 'Fuera de Pichincha')"
                    x-on:keydown.enter.prevent="seleccionarZona('Fuera de Pichincha', 'Fuera de Pichincha')"
                    x-on:keydown.space.prevent="seleccionarZona('Fuera de Pichincha', 'Fuera de Pichincha')"
                >
                    <x-slot:icono>
                        <flux:icon name="map" class="size-5 text-science-blue" />
                    </x-slot:icono>
                </x-gestionprestamosrecepciones::radio-card>
            </div>
        </div>

    </div>

    <div x-show="origen === 'Exterior (Extranjero)'" x-cloak>
        <flux:callout variant="info" icon="information-circle">
            <flux:text class="text-sm">
                {{ \App\Support\WizardCopy::text('origen.aviso_exterior') }}
            </flux:text>
        </flux:callout>
    </div>

</div>
