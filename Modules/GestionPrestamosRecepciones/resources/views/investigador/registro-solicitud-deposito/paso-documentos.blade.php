<div class="space-y-6" x-data="{ total: {{ count($documentosRequeridos) }} }">

    <div class="border-b border-blue-navy/10 pb-5">
        <flux:heading size="lg" level="2" class="font-display tracking-tight text-blue-navy">Documentos oficiales</flux:heading>
        <flux:text class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">
            Adjunta archivos PDF legibles. HubDigital los clasificará por su contenido, leerá códigos y fechas y comprobará que pertenezcan al mismo expediente.
        </flux:text>
    </div>

    {{-- Procesando documentos (polling activo) --}}
    @if($extraccionProcesando)
        <div wire:poll.500ms="verificarExtraccion" class="space-y-4 rounded-xl border border-science-blue/30 bg-science-blue/5 p-6" role="status" aria-live="polite">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-science-blue/15">
                    <flux:icon name="arrow-path" class="size-5 text-science-blue animate-spin" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-text-primary">Analizando documentos…</p>
                    <p class="text-xs text-text-secondary">
                        {{ count($documentosProcesados) }} de {{ count($documentosCargados) }} documentos procesados.
                    </p>
                </div>
            </div>
            <div class="space-y-2">
                @foreach($documentosCargados as $nombre => $ruta)
                    @php $procesado = in_array($nombre, $documentosProcesados, true); @endphp
                    <div class="flex items-center gap-2 text-sm {{ $procesado ? 'text-text-primary' : 'text-text-secondary' }}">
                        @if($procesado)
                            <flux:icon name="check-circle" class="size-4 shrink-0 text-success" />
                        @else
                            <flux:icon name="arrow-path" class="size-4 shrink-0 text-science-blue animate-spin" />
                        @endif
                        <span>{{ $nombre }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @else

    {{-- Intervención curatorial activa --}}
    @if($intervencionCuratoriaActiva)

        <div wire:key="card-intervencion" class="rounded-xl border border-warning/40 bg-warning/5 p-6 space-y-5">

            {{-- Estado --}}
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-warning/15">
                    <flux:icon name="pause-circle" class="size-5 text-warning" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-text-primary">Solicitud pausada — en espera de asesoría</p>
                    <p class="text-xs text-text-secondary">N.º {{ $numeroSolicitud }}</p>
                </div>
            </div>

            {{-- Qué pasó --}}
            <p class="text-sm text-text-secondary">
                La carga de documentos ha sido pausada. El funcionario responsable revisará tu caso y se pondrá en contacto contigo directamente para orientarte en el proceso.
            </p>

            {{-- Próximos pasos --}}
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">¿Qué sigue?</p>
                <ul class="space-y-2">
                    <li class="flex items-start gap-2 text-sm text-text-primary">
                        <flux:icon name="check-circle" class="size-4 text-success shrink-0 mt-0.5" />
                        Tu solicitud quedó registrada con el estado <strong>Pausada para asesoría</strong>.
                    </li>
                    <li class="flex items-start gap-2 text-sm text-text-primary">
                        <flux:icon name="envelope" class="size-4 text-science-blue shrink-0 mt-0.5" />
                        Recibirás una notificación cuando el funcionario responsable inicie el contacto contigo.
                    </li>
                    <li class="flex items-start gap-2 text-sm text-text-primary">
                        <flux:icon name="clock" class="size-4 text-text-secondary shrink-0 mt-0.5" />
                        No es necesario que hagas nada más por ahora.
                    </li>
                </ul>
            </div>

            <flux:button icon="arrow-left" href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate>
                Ver mis depósitos
            </flux:button>

        </div>

    @else

    <div wire:key="formulario-documentos" class="space-y-4">

        {{-- Error de documentos faltantes --}}
        <flux:error name="documentos" />

        @if($estadoValidacionContenido === 'rechazado' || !empty($erroresDocumentales))
            <flux:callout variant="danger" icon="x-circle" heading="Los documentos no superaron la validación de contenido">
                <p class="mb-2 text-sm">HubDigital revisó la estructura, códigos, titulares, proyecto y fechas; no se basa en el nombre del archivo.</p>
                <ul class="list-disc space-y-1 pl-5 text-sm">
                    @foreach($erroresDocumentales as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs">Reemplaza el archivo incorrecto y vuelve a ejecutar el análisis.</p>
            </flux:callout>
        @endif

        @if(!empty($advertenciasDocumentales))
            <flux:callout variant="warning" icon="exclamation-triangle" heading="Aspectos que requieren confirmación humana">
                <ul class="list-disc space-y-1 pl-5 text-sm">
                    @foreach($advertenciasDocumentales as $advertencia)
                        <li>{{ $advertencia }}</li>
                    @endforeach
                </ul>
            </flux:callout>
        @endif

        {{-- Dropzones dinámicas --}}
        @if(!empty($documentosRequeridos))
            @php
                $plantillas = [
                    'Formato solicitud depósito' => asset('plantillas/depositos/formato-solicitud-deposito.pdf'),
                    'Formato solicitud donación' => asset('plantillas/depositos/formato-solicitud-donacion.pdf'),
                    'Copia de la autorización de recolección (MAE)' => asset('plantillas/depositos/autorizacion-mae-ejemplo.pdf'),
                    'Copia del permiso de movilización' => asset('plantillas/depositos/permiso-movilizacion-ejemplo.pdf'),
                    'Documento de explicación de motivos y/o carta de justificación (institucional o personal)' => asset('plantillas/depositos/carta-justificacion-ejemplo.pdf'),
                    'Documento de procedencia de los especimenes' => asset('plantillas/depositos/carta-procedencia-ejemplo.pdf'),
                    'Carta de cesión de derechos / origen lícito' => asset('plantillas/depositos/carta-cesion-ejemplo.pdf'),
                    'Carta de delegación / justificación de tercero' => asset('plantillas/depositos/carta-delegacion-ejemplo.pdf'),
                ];
                $plantillasDisponibles = array_intersect_key($plantillas, array_flip($documentosRequeridos));

                $ayudas = [
                    'Formato solicitud depósito'
                        => 'Formulario oficial de la colección que debes completar para iniciar tu solicitud. El ejemplo de referencia te muestra cómo luce correctamente llenado.',
                    'Formato solicitud donación'
                        => 'Formulario oficial para formalizar la transferencia permanente de los especímenes. Consulta el ejemplo de referencia para ver cómo debe quedar.',
                    'Copia de la autorización de recolección (MAE)'
                        => 'Oficio emitido por el Ministerio del Ambiente que concede la autorización de recolección. El sistema extraerá el número, titular, organización, proyecto, grupos biológicos, fechas y firma.',
                    'Copia del permiso de movilización'
                        => 'Guía que ampara el traslado. El sistema extraerá su número, autorización relacionada, vigencia, origen, destino y códigos de muestra para llenar la matriz.',
                    'Documento de explicación de motivos y/o carta de justificación (institucional o personal)'
                        => 'Carta redactada por ti o tu institución que explica por qué no cuentas con permisos del MAE. El ejemplo te muestra el tipo de contenido y tono esperados.',
                    'Documento de procedencia de los especimenes'
                        => 'Este documento explica y justifica cómo obtuviste los especímenes que deseas depositar. Debe incluir el origen, la procedencia y cualquier información que respalde la legalidad de su obtención.',
                    'Carta de cesión de derechos / origen lícito'
                        => 'Documento que certifica que los especímenes se donan voluntariamente y que su obtención fue legal. El ejemplo te orienta sobre su contenido.',
                    'Carta de delegación / justificación de tercero'
                        => 'Si el nombre en los documentos no coincide con tu perfil, adjunta esta carta explicando el motivo o autorizando a otra persona a realizar el trámite.',
                ];
            @endphp

            <section class="space-y-3" aria-labelledby="documentos-requeridos-titulo">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 id="documentos-requeridos-titulo" class="text-sm font-semibold text-blue-navy">Archivos requeridos</h3>
                        <p class="mt-1 text-xs leading-5 text-text-secondary">Puedes cargarlos con cualquier nombre; validaremos el tipo mediante su contenido.</p>
                    </div>
                    <p class="font-mono text-xs text-text-secondary">{{ count($documentosCargados) }}/{{ count($documentosRequeridos) }} cargados</p>
                </div>
                @foreach($documentosRequeridos as $docNombre)
                    @php $prop = $this->propiedadParaDocumento($docNombre); @endphp
                    <x-gestionprestamosrecepciones::dropzone
                        :nombre="$docNombre"
                        :propiedad="$prop"
                        :requerido="true"
                        :cargado="isset($documentosCargados[$docNombre])"
                        :archivo-nombre="$nombresArchivosOriginales[$docNombre] ?? null"
                        :plantilla="$plantillasDisponibles[$docNombre] ?? null"
                        :ayuda="$ayudas[$docNombre] ?? null"
                        role="button"
                        tabindex="0"
                        aria-label="Cargar documento: {{ $docNombre }}"
                        x-on:keydown.enter.prevent="if (!cargado) $refs.fileInput.click()"
                        x-on:keydown.space.prevent="if (!cargado) $refs.fileInput.click()"
                    />
                @endforeach
            </section>
        @else
            <div class="rounded-lg border border-dashed border-border p-8 text-center">
                <flux:icon name="document-text" class="size-8 text-text-secondary mx-auto mb-2" />
                <p class="text-sm text-text-secondary">Cargando documentos requeridos…</p>
            </div>
        @endif

        {{-- Sección de intervención curatorial --}}
        <div class="space-y-3 border-t border-blue-navy/10 pt-5">
            <div class="flex items-start gap-3">
                <flux:icon name="question-mark-circle" class="size-5 text-text-secondary shrink-0 mt-0.5" />
                <div class="flex-1">
                    <p class="text-sm font-semibold text-text-primary">¿No cuentas con ningún documento disponible?</p>
                    <p class="text-xs text-text-secondary mt-0.5">
                        Si no cuentas con ningún documento, puedes solicitar la intervención directa del funcionario responsable.
                        La carga documental se pausará y él se pondrá en contacto contigo para orientarte.
                    </p>
                </div>
            </div>
            <flux:button
                variant="outline"
                size="sm"
                wire:click="solicitarIntervencion"
                wire:loading.attr="disabled"
                wire:target="solicitarIntervencion"
                icon="hand-raised"
                icon:loading="arrow-path"
                class="text-warning border-warning/40 hover:bg-warning/10"
            >
                Solicitar asistencia
            </flux:button>
        </div>

    </div>{{-- fin wire:key="formulario-documentos" --}}

    @endif

    @endif {{-- fin @if(!$extraccionProcesando) --}}

</div>
