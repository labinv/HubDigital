<div
    class="space-y-5 pb-8"
    x-data="{
        domainError: null,
        tipoTramite: $wire.entangle('tipoTramite'),
        origenRecoleccion: $wire.entangle('origenRecoleccion'),
        situacionRegulatoria: $wire.entangle('situacionRegulatoria'),
        declaracionAceptada: $wire.entangle('declaracionAceptada'),
        limiteAlcanzado: $wire.entangle('limiteAlcanzado'),
        matrizCargada: $wire.entangle('matrizCargada'),
        solicitudFirmada: $wire.entangle('solicitudFirmada'),
    }"
    x-on:domain-error.window="domainError = $event.detail.message; setTimeout(() => domainError = null, 6000)"
>
    {{-- Domain error global --}}
    <div x-show="domainError" x-transition class="flex items-start gap-3 rounded-lg border border-error bg-error/5 p-4" role="alert" aria-live="assertive">
        <flux:icon name="x-circle" class="size-5 text-error shrink-0 mt-0.5" />
        <p class="text-sm text-error font-medium" x-text="domainError"></p>
    </div>

    {{-- Corrección de un rechazo subsanable: recordatorio de las observaciones --}}
    @if($modoCorreccion && $paso < 7)
        <div class="rounded-lg border border-warning/40 bg-warning/5 p-4 flex items-start gap-3">
            <flux:icon name="exclamation-triangle" class="size-5 text-warning shrink-0 mt-0.5" />
            <div class="min-w-0">
                <p class="text-sm font-medium text-text-primary">Estás corrigiendo una solicitud devuelta por la curaduría</p>
                @if($comentarioCurador)
                    <p class="text-sm text-text-secondary mt-0.5"><span class="font-medium text-text-primary">Observaciones:</span> {{ $comentarioCurador }}</p>
                @endif
                <p class="text-xs text-text-secondary mt-1">Corrige lo indicado y vuelve a enviar la solicitud para revisión.</p>
            </div>
        </div>
    @endif

    @if($paso < 7)
        {{-- Breadcrumbs --}}
        <div class="border-b border-blue-navy/10 pb-3">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-solicitudes') }}">
                    Mis solicitudes
                </flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Nueva solicitud de depósito</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>

        {{-- Borrador restaurado --}}
        @if($borradorRestaurado && ! $modoCorreccion && $paso < 6)
            <div class="rounded-lg border border-science-blue/30 bg-science-blue/5 p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <div class="flex items-center gap-3">
                    <flux:icon name="bookmark" class="size-5 text-science-blue shrink-0" />
                    <div>
                        <p class="text-sm font-medium text-text-primary">Borrador pendiente recuperado</p>
                        <p class="text-xs text-text-secondary mt-0.5">Puedes continuar donde lo dejaste o descartar para empezar de nuevo.</p>
                    </div>
                </div>
                <flux:modal.trigger name="confirmar-descartar-borrador">
                    <flux:button
                        variant="outline"
                        size="sm"
                        icon="trash"
                        class="shrink-0"
                    >
                        Descartar
                    </flux:button>
                </flux:modal.trigger>
            </div>

            <flux:modal name="confirmar-descartar-borrador" class="max-w-sm">
                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg">Descartar borrador</flux:heading>
                        <flux:text class="text-text-secondary mt-1">
                            Se eliminará el borrador y todos los documentos cargados. Esta acción no se puede deshacer.
                        </flux:text>
                    </div>
                    <div class="flex justify-end gap-3">
                        <flux:modal.close>
                            <flux:button variant="ghost">Cancelar</flux:button>
                        </flux:modal.close>
                        <flux:button variant="danger" wire:click="descartarBorrador" icon="trash">
                            Descartar
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif

        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4 pt-1">
            <div class="max-w-3xl">
                <flux:heading size="xl" level="1" class="font-display tracking-tight text-blue-navy">Nueva solicitud de depósito</flux:heading>
                <flux:text class="mt-2 max-w-2xl leading-6 text-text-secondary">
                    Completa el expediente de depósito o donación de material biológico. Puedes guardar el avance y continuar después.
                </flux:text>
            </div>
            @if($numeroSolicitud)
                <div class="self-start border-l-2 border-bio-green pl-3 text-right">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-text-secondary">N.º de solicitud</p>
                    <p class="mt-1 font-mono text-xs font-semibold text-blue-navy">{{ $numeroSolicitud }}</p>
                </div>
            @endif
        </div>

        {{-- Stepper --}}
        <div aria-label="Progreso de la solicitud">
            <div class="mb-2 flex items-center justify-between sm:hidden">
                <p class="text-sm font-semibold text-blue-navy">Paso {{ $paso }} de 6</p>
                <p class="text-xs text-text-secondary">El avance se guarda por etapas</p>
            </div>
            <x-gestionprestamosrecepciones::wizard-stepper
                :pasos="[
                    ['label' => 'Trámite',    'sub' => 'Modalidad'],
                    ['label' => 'Origen',     'sub' => 'Procedencia'],
                    ['label' => 'Documentos', 'sub' => 'Lectura asistida'],
                    ['label' => 'Datos MEPN', 'sub' => 'Formulario'],
                    ['label' => 'Detalle',    'sub' => 'Taxonomía'],
                    ['label' => 'Envío',      'sub' => 'Firma'],
                ]"
                :pasoActual="$paso"
                :pasosCompletados="$pasosCompletados"
            />
        </div>
    @endif

    @php
        $ayudaPorPaso = [
            1 => [
                'titulo' => 'Define el trámite',
                'texto' => 'Selecciona la modalidad que corresponde al destino del material.',
                'items' => ['Depósito: custodia temporal', 'Donación: transferencia definitiva', 'El sistema comprobará el límite anual'],
            ],
            2 => [
                'titulo' => 'Declara la procedencia',
                'texto' => 'Esta información permite calcular los documentos que exige tu expediente.',
                'items' => ['Indica el origen del material', 'Selecciona la situación regulatoria', 'Confirma provincia y localidad'],
            ],
            3 => [
                'titulo' => 'Lectura asistida',
                'texto' => 'Los nombres de archivo no determinan su validez: analizamos el contenido de cada PDF.',
                'items' => ['Clasificamos el documento', 'Leemos códigos y fechas', 'Comparamos el expediente', 'Tú confirmas el resultado'],
            ],
            4 => [
                'titulo' => 'Datos oficiales MEPN',
                'texto' => 'Revisa lo recuperado de tus documentos y completa únicamente la información pendiente.',
                'items' => ['Perfil del consultor', 'Permisos y movilización', 'Cantidades y localidad'],
            ],
            5 => [
                'titulo' => 'Detalle biológico',
                'texto' => 'Registra cada espécimen o lote con vocabularios controlados y taxonomía verificable.',
                'items' => ['Busca en EPN o GBIF', 'Confirma el taxón', 'Valida los registros pendientes'],
            ],
            6 => [
                'titulo' => 'Revisa, firma y envía',
                'texto' => 'Comprueba el resumen antes de firmar. Tu certificado y clave permanecen en este navegador.',
                'items' => ['Genera el PDF oficial', 'Firma con tu archivo P12', 'Envía a revisión curatorial'],
            ],
        ];
        $ayudaActual = $ayudaPorPaso[$paso] ?? null;
    @endphp

    {{-- Superficie principal de trabajo --}}
    <div class="overflow-hidden rounded-xl border border-blue-navy/15 bg-surface shadow-sm">
        <div @class(['grid', 'lg:grid-cols-[minmax(0,1fr)_17rem]' => $paso < 7])>

            {{-- Step content --}}
            <div class="min-w-0 p-4 sm:p-6 lg:p-8" wire:key="paso-{{ $paso }}">
                @if($paso === 1)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-tramite')
                @elseif($paso === 2)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-origen')
                @elseif($paso === 3)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-documentos')
                @elseif($paso === 4)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-datos')
                @elseif($paso === 5)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-matriz')
                @elseif($paso === 6 || $paso === 7)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-envio')
                @endif

                @if($paso < 7 && $ayudaActual)
                    <details class="mt-8 border-t border-blue-navy/10 pt-5 lg:hidden">
                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-blue-navy focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue">
                            Orientación para este paso
                            <flux:icon name="chevron-down" class="size-4" />
                        </summary>
                        <p class="mt-2 text-sm leading-6 text-text-secondary">{{ $ayudaActual['texto'] }}</p>
                        <ul class="mt-3 space-y-2 text-sm text-text-secondary">
                            @foreach($ayudaActual['items'] as $item)
                                <li class="flex gap-2"><flux:icon name="check" class="mt-0.5 size-4 shrink-0 text-bio-green" /><span>{{ $item }}</span></li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>

            @if($paso < 7 && $ayudaActual)
                <aside class="hidden border-l border-blue-navy/10 bg-[#F8FAFC] p-6 lg:block" aria-labelledby="ayuda-paso-titulo">
                    <div class="sticky top-6">
                        <div class="flex size-10 items-center justify-center rounded-lg border border-science-blue/20 bg-white text-science-blue">
                            <flux:icon name="light-bulb" class="size-5" />
                        </div>
                        <h2 id="ayuda-paso-titulo" class="mt-5 font-display text-lg font-semibold text-blue-navy">{{ $ayudaActual['titulo'] }}</h2>
                        <p class="mt-2 text-sm leading-6 text-text-secondary">{{ $ayudaActual['texto'] }}</p>
                        <ol class="mt-5 space-y-4">
                            @foreach($ayudaActual['items'] as $indice => $item)
                                <li class="grid grid-cols-[1.5rem_1fr] gap-2 text-sm leading-5 text-text-secondary">
                                    <span class="flex size-6 items-center justify-center rounded-full border border-bio-green/40 bg-white font-mono text-[11px] font-semibold text-bio-green">{{ $indice + 1 }}</span>
                                    <span class="pt-0.5">{{ $item }}</span>
                                </li>
                            @endforeach
                        </ol>
                        <div class="mt-6 border-t border-blue-navy/10 pt-4">
                            <p class="flex items-start gap-2 text-xs leading-5 text-text-secondary">
                                <flux:icon name="shield-check" class="mt-0.5 size-4 shrink-0 text-bio-green" />
                                La información se guarda en tu expediente y solo la consulta el personal autorizado.
                            </p>
                        </div>
                    </div>
                </aside>
            @endif
        </div>

        {{-- Footer navigation --}}
        @if($paso < 7)
            <div class="flex items-center justify-between gap-3 border-t border-blue-navy/10 bg-[#F8FAFC] px-4 py-3 sm:px-6 sm:py-4 lg:px-8">
                <div>
                    @if($paso > 1 && !$extraccionProcesando)
                        <flux:button variant="ghost" wire:click="retroceder" icon="arrow-left">
                            Atrás
                        </flux:button>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    @if($paso === 1)
                        <flux:button
                            variant="primary"
                            icon-trailing="arrow-right"
                            wire:click="avanzarPaso1"
                            wire:loading.attr="disabled"
                            wire:target="avanzarPaso1"
                            x-bind:disabled="!tipoTramite || limiteAlcanzado"
                        >
                            <flux:icon wire:loading wire:target="avanzarPaso1" name="arrow-path" class="animate-spin size-4 mr-1" />
                            Continuar
                        </flux:button>
                    @elseif($paso === 2)
                        <flux:button
                            variant="primary"
                            icon-trailing="arrow-right"
                            wire:click="guardarPasoDos"
                            wire:loading.attr="disabled"
                            wire:target="guardarPasoDos"
                            x-bind:disabled="!origenRecoleccion || !situacionRegulatoria"
                        >
                            <flux:icon wire:loading wire:target="guardarPasoDos" name="arrow-path" class="animate-spin size-4 mr-1" />
                            Guardar y continuar
                        </flux:button>
                    @elseif($paso === 3)
                        @if(!$intervencionCuratoriaActiva && !$extraccionProcesando)
                            <flux:button
                                variant="primary"
                                icon-trailing="arrow-right"
                                wire:click="guardarPasoTres"
                            >
                                Validar documentos
                            </flux:button>
                        @endif
                    @elseif($paso === 4)
                        <flux:button
                            variant="primary"
                            icon-trailing="arrow-right"
                            wire:click="guardarPasoCuatro"
                            wire:loading.attr="disabled"
                            wire:target="guardarPasoCuatro"
                        >
                            <flux:icon wire:loading wire:target="guardarPasoCuatro" name="arrow-path" class="animate-spin size-4 mr-1" />
                            Continuar
                        </flux:button>
                    @elseif($paso === 5)
                        <flux:button
                            variant="primary"
                            icon-trailing="arrow-right"
                            wire:click="guardarPasoCinco"
                            wire:loading.attr="disabled"
                            wire:target="guardarPasoCinco"
                        >
                            <flux:icon wire:loading wire:target="guardarPasoCinco" name="arrow-path" class="animate-spin size-4 mr-1" />
                            Revisar y enviar
                        </flux:button>
                    @elseif($paso === 6)
                        <flux:button
                            variant="primary"
                            icon-trailing="paper-airplane"
                            wire:click="enviarSolicitud"
                            wire:loading.attr="disabled"
                            wire:target="enviarSolicitud"
                            x-bind:disabled="!declaracionAceptada || !solicitudFirmada"
                        >
                            <flux:icon wire:loading wire:target="enviarSolicitud" name="arrow-path" class="animate-spin size-4 mr-1" />
                            Enviar solicitud
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif

    </div>

    {{-- Toast teleportado al body — fuera del DOM de Livewire, sin morph --}}
    @teleport('body')
        <div
            x-data="{
                show: false,
                message: '',
                variant: 'warning',
                timer: null,
                showToast(data) {
                    this.message = data.message;
                    this.variant = data.variant || 'warning';
                    this.show = true;
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => this.show = false, 5000);
                }
            }"
            x-on:show-toast.window="showToast($event.detail)"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-x-full"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-full"
            x-bind:class="variant === 'error'
                ? 'border-error/50 bg-error/5 ring-1 ring-error/20'
                : 'border-warning/50 bg-warning/5 ring-1 ring-warning/20'"
            class="rounded-xl border bg-surface px-5 py-4 flex items-start gap-3"
            style="display: none; position: fixed; top: 1.25rem; right: 1.5rem; z-index: 9999; width: 22rem; max-width: calc(100vw - 3rem); box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 4px 16px rgba(0,0,0,0.1);"
        >
            <div x-show="variant === 'error'" class="flex-shrink-0 mt-0.5">
                <div class="flex items-center justify-center size-8 rounded-full bg-error/10">
                    <svg class="size-5 text-error" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div x-show="variant !== 'error'" class="flex-shrink-0 mt-0.5">
                <div class="flex items-center justify-center size-8 rounded-full bg-warning/10">
                    <svg class="size-5 text-warning" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p x-show="variant === 'error'" class="text-sm font-bold text-error">Error de validación</p>
                <p x-show="variant !== 'error'" class="text-sm font-bold text-warning">Atención</p>
                <p class="text-sm text-text-primary mt-1 leading-snug font-normal" x-text="message"></p>
            </div>
            <button x-on:click="show = false" class="flex-shrink-0 p-1 rounded-md text-text-secondary hover:text-text-primary hover:bg-bg-main transition-colors">
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endteleport
</div>
