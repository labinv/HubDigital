<div
    class="space-y-6"
    x-data="{
        tipo: @js($tipoTramite),
        seleccionar(val) {
            this.tipo = val;
            $dispatch('radio-card-select', { grupo: 'tipoTramite', valor: val });
            $wire.set('tipoTramite', val);
        }
    }"
>

    <div class="border-b border-blue-navy/10 pb-5">
        <flux:heading size="lg" level="2" class="font-display tracking-tight text-blue-navy">Tipo de trámite</flux:heading>
        <flux:text class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">
            Indica si el material permanecerá temporalmente en custodia o pasará a formar parte definitiva de la colección.
        </flux:text>
    </div>

    @if($limiteAlcanzado)
        <flux:callout variant="danger" icon="x-circle">
            <flux:heading>Límite anual de depósitos alcanzado</flux:heading>
            <flux:text>Has utilizado los 3 depósitos permitidos este año. Puedes continuar registrando como <strong>Donación</strong>.</flux:text>
        </flux:callout>
    @endif

    <flux:error name="tipoTramite" />

    <div class="grid gap-4 sm:grid-cols-2" wire:ignore aria-label="Modalidad del trámite">
        <x-gestionprestamosrecepciones::radio-card
            :activo="$tipoTramite === 'Depósito'"
            :deshabilitado="$limiteAlcanzado"
            titulo="Depósito"
            grupo="tipoTramite"
            descripcion="Custodia temporal con devolución programada. Cupo anual limitado a 3 solicitudes."
            role="radio"
            x-bind:aria-checked="active"
            aria-disabled="{{ $limiteAlcanzado ? 'true' : 'false' }}"
            tabindex="{{ $limiteAlcanzado ? '-1' : '0' }}"
            x-on:click="!{{ $limiteAlcanzado ? 'true' : 'false' }} && seleccionar('Depósito')"
            x-on:keydown.enter.prevent="!{{ $limiteAlcanzado ? 'true' : 'false' }} && seleccionar('Depósito')"
            x-on:keydown.space.prevent="!{{ $limiteAlcanzado ? 'true' : 'false' }} && seleccionar('Depósito')"
        >
            <x-slot:icono>
                <flux:icon name="archive-box" class="size-5 text-blue-navy" />
            </x-slot:icono>
            <x-slot:badge>
                @if($solicitudesPreviasDeposito >= 3)
                    <flux:badge color="red" size="sm">Cupo lleno</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">{{ $solicitudesPreviasDeposito }}/3</flux:badge>
                @endif
            </x-slot:badge>
        </x-gestionprestamosrecepciones::radio-card>

        <x-gestionprestamosrecepciones::radio-card
            :activo="$tipoTramite === 'Donación'"
            titulo="Donación"
            grupo="tipoTramite"
            descripcion="Cesión definitiva al patrimonio de la colección. Requiere carta de cesión de derechos."
            role="radio"
            x-bind:aria-checked="active"
            tabindex="0"
            x-on:click="seleccionar('Donación')"
            x-on:keydown.enter.prevent="seleccionar('Donación')"
            x-on:keydown.space.prevent="seleccionar('Donación')"
        >
            <x-slot:icono>
                <flux:icon name="heart" class="size-5 text-bio-green" />
            </x-slot:icono>
            <x-slot:badge>
                <flux:badge color="green" size="sm">Sin límite</flux:badge>
            </x-slot:badge>
        </x-gestionprestamosrecepciones::radio-card>
    </div>

    <div x-show="tipo === 'Depósito'" x-cloak class="space-y-3">
        <flux:callout variant="info" icon="information-circle">
            <flux:text class="text-sm">
                El <strong>Depósito</strong> es temporal. Los especímenes permanecerán en custodia y serán devueltos según el acta de préstamo correspondiente.
            </flux:text>
        </flux:callout>
        <div class="border-l-2 border-blue-navy/20 bg-[#F8FAFC] px-4 py-3">
            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.1em] text-blue-navy">Documentos que necesitarás</p>
            <ul class="space-y-2 text-xs leading-5 text-text-secondary">
                <li class="flex items-center gap-2">
                    <flux:icon name="document-text" class="size-3.5 text-text-secondary shrink-0" />
                    Solicitud de depósito generada y firmada dentro de HubDigital
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="document-check" class="size-3.5 text-text-secondary shrink-0" />
                    Autorización de recolección y/o permiso de movilización <span class="text-text-secondary/60">(según origen)</span>
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="document-text" class="size-3.5 text-text-secondary shrink-0" />
                    Carta de procedencia o justificación <span class="text-text-secondary/60">(según situación regulatoria)</span>
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="table-cells" class="size-3.5 text-text-secondary shrink-0" />
                    Datos de depósito material MEPN y detalle biológico, completados mediante formularios guiados
                </li>
            </ul>
            <p class="text-xs text-text-secondary/60 mt-2 italic">Los documentos exactos se determinarán en el siguiente paso según el origen de los especímenes.</p>
        </div>
    </div>
    <div x-show="tipo === 'Donación'" x-cloak class="space-y-3">
        <flux:callout variant="info" icon="information-circle">
            <flux:text class="text-sm">
                La <strong>Donación</strong> transfiere permanentemente la propiedad de los especímenes a la colección. Se requiere carta de cesión de derechos con firma del donante.
            </flux:text>
        </flux:callout>
        <div class="border-l-2 border-bio-green/30 bg-[#F8FAFC] px-4 py-3">
            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.1em] text-blue-navy">Documentos que necesitarás</p>
            <ul class="space-y-2 text-xs leading-5 text-text-secondary">
                <li class="flex items-center gap-2">
                    <flux:icon name="document-text" class="size-3.5 text-text-secondary shrink-0" />
                    Solicitud de donación generada y firmada dentro de HubDigital
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="document-check" class="size-3.5 text-text-secondary shrink-0" />
                    Carta de cesión de derechos / origen lícito
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="table-cells" class="size-3.5 text-text-secondary shrink-0" />
                    Datos de depósito material MEPN y detalle biológico, completados mediante formularios guiados
                </li>
            </ul>
        </div>
    </div>

</div>
