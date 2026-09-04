@if($paso === 7)

    {{-- ── Pantalla de confirmación final ──────────────────────────────────────── --}}
    @php
        $esExito  = in_array($estadoFinal, ['Pendiente de Revisión por Curaduría', 'Registrada']);
        $esAviso  = in_array($estadoFinal, ['Pausada para Asesoría', 'Requiere Corrección']);
        $esError  = $estadoFinal === 'Rechazada';

        $icono  = $esExito ? 'check-circle' : ($esAviso ? 'exclamation-triangle' : 'x-circle');
        $colorIcono = $esExito ? 'text-success' : ($esAviso ? 'text-warning' : 'text-error');
        $colorFondo = $esExito ? 'bg-success/10' : ($esAviso ? 'bg-warning/10' : 'bg-error/10');
        $colorBorde = $esExito ? 'border-success/30' : ($esAviso ? 'border-warning/30' : 'border-error/30');

        $titulo = match($estadoFinal) {
            'Pendiente de Revisión por Curaduría' => 'Solicitud enviada · pendiente de revisión por curaduría',
            'Registrada'                          => 'Solicitud registrada exitosamente',
            'Pausada para Asesoría'   => 'Solicitud pausada — en espera de asesoría',
            'Requiere Corrección'                 => 'La solicitud requiere corrección',
            'Rechazada'                           => 'Solicitud rechazada por límite anual',
            default                               => 'Solicitud procesada',
        };

        $subtitulo = match($estadoFinal) {
            'Pendiente de Revisión por Curaduría' => 'Tu solicitud ha sido remitida al equipo curatorial. Recibirás notificación cuando inicie la revisión.',
            'Registrada'                          => 'El registro fue aceptado y archivado en la colección.',
            'Pausada para Asesoría'   => 'El funcionario responsable se pondrá en contacto contigo para guiar el caso documental.',
            'Requiere Corrección'                 => 'Algunos documentos no cumplen los requisitos. Revisa las observaciones y reenvía.',
            'Rechazada'                           => 'No es posible continuar. El cupo anual de depósitos ha sido alcanzado.',
            default                               => '',
        };
    @endphp

    <div class="py-8 flex flex-col items-center text-center gap-6">

        {{-- Icono hero --}}
        <div class="size-20 rounded-full {{ $colorFondo }} flex items-center justify-center">
            <flux:icon name="{{ $icono }}" class="size-10 {{ $colorIcono }}" />
        </div>

        {{-- Título y subtítulo --}}
        <div class="space-y-2">
            <flux:heading size="lg" level="2">{{ $titulo }}</flux:heading>
            <flux:text class="text-text-secondary max-w-md mx-auto">{{ $subtitulo }}</flux:text>
        </div>

        {{-- Mini ficha de resumen --}}
        <div class="w-full max-w-md text-left rounded-lg border {{ $colorBorde }} bg-bg-main divide-y divide-border">
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-text-secondary">ID de solicitud</span>
                <span class="font-mono text-xs font-medium text-text-primary">{{ $numeroSolicitud }}</span>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-text-secondary">Tipo</span>
                <strong class="text-sm text-text-primary">{{ $tipoTramite }}</strong>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-text-secondary">Origen</span>
                <span class="text-sm text-text-primary text-right">
                    {{ $origenRecoleccion }}
                    @if($situacionRegulatoria && $situacionRegulatoria !== 'Proviene de colección foránea')
                        <span class="text-text-secondary"> · {{ $situacionRegulatoria }}</span>
                    @endif
                </span>
            </div>
            @if($provincia)
                <div class="flex items-center justify-between px-4 py-3">
                    <span class="text-xs text-text-secondary">Provincia</span>
                    <span class="text-sm text-text-primary">
                        {{ $provincia }}@if($localidad) / {{ $localidad }}@endif
                    </span>
                </div>
            @endif
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-text-secondary">Estado</span>
                <x-gestionprestamosrecepciones::deposito-status-badge estado="{{ $estadoFinal }}" />
            </div>
        </div>

        {{-- Línea de tiempo --}}
        @if($esExito)
            <div class="w-full max-w-md text-left space-y-3">
                <flux:heading size="sm" level="3">Historial de la solicitud</flux:heading>
                <ol class="relative border-l border-border ml-3 space-y-4">
                    <li class="pl-5">
                        <div class="absolute -left-1.5 mt-1 size-3 rounded-full bg-success"></div>
                        <p class="text-xs text-text-secondary">{{ now()->format('d M Y · H:i') }}</p>
                        <p class="text-sm font-medium text-text-primary">Solicitud creada</p>
                    </li>
                    <li class="pl-5">
                        <div class="absolute -left-1.5 mt-1 size-3 rounded-full bg-success"></div>
                        <p class="text-xs text-text-secondary">{{ now()->format('d M Y · H:i') }}</p>
                        <p class="text-sm font-medium text-text-primary">Documentos cargados y validados</p>
                    </li>
                    @if($matrizCargada)
                        <li class="pl-5">
                            <div class="absolute -left-1.5 mt-1 size-3 rounded-full bg-success"></div>
                            <p class="text-xs text-text-secondary">{{ now()->format('d M Y · H:i') }}</p>
                            <p class="text-sm font-medium text-text-primary">Matriz Darwin Core validada</p>
                        </li>
                    @endif
                    <li class="pl-5">
                        <div class="absolute -left-1.5 mt-1 size-3 rounded-full bg-success"></div>
                        <p class="text-xs text-text-secondary">{{ now()->format('d M Y · H:i') }}</p>
                        <p class="text-sm font-medium text-text-primary">Solicitud enviada al equipo curatorial</p>
                    </li>
                    <li class="pl-5">
                        <div class="absolute -left-1.5 mt-1 size-3 rounded-full bg-border border-2 border-border"></div>
                        <p class="text-xs text-text-secondary">Pendiente</p>
                        <p class="text-sm text-text-secondary">Revisión por curaduría · ETA 3 días hábiles</p>
                    </li>
                </ol>
            </div>
        @endif

        {{-- Acciones --}}
        <div class="flex gap-3 flex-wrap justify-center">
            <flux:button
                variant="filled"
                icon="document-text"
                wire:navigate
                href="{{ route('prestamos.investigador.mis-depositos') }}"
            >
                Ver mis solicitudes
            </flux:button>
            <a
                wire:navigate
                href="{{ route('prestamos.investigador.deposito.crear') }}"
                class="flex items-center gap-1.5 text-sm text-text-secondary hover:text-text-primary transition-colors"
            >
                <flux:icon name="plus" class="size-4" />
                Nueva solicitud
            </a>
        </div>

    </div>

@else

    {{-- ── Paso 5: Revisar y enviar ────────────────────────────────────────────── --}}
    <div class="space-y-6">

        <div class="border-b border-blue-navy/10 pb-5">
            <flux:heading size="lg" level="2" class="font-display tracking-tight text-blue-navy">Revisar, firmar y enviar</flux:heading>
            <flux:text class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">
                Comprueba la información, genera el documento institucional y fírmalo antes de remitir el expediente al equipo curatorial.
            </flux:text>
        </div>

        <flux:callout variant="info" icon="information-circle">
            <flux:text class="text-sm">
                Tu solicitud será evaluada por curaduría en máximo <strong>3 días hábiles</strong>.
                Recibirás notificación en cada cambio de estado.
            </flux:text>
        </flux:callout>

        {{-- Resumen de la solicitud --}}
        <div class="space-y-3">
            <flux:heading size="sm" level="3">Resumen de la solicitud</flux:heading>

            <div class="grid gap-3 sm:grid-cols-2">

                <div class="rounded-lg border border-border bg-bg-main p-3 space-y-0.5">
                    <p class="text-xs text-text-secondary">Tipo de trámite</p>
                    <p class="text-sm font-semibold text-text-primary">{{ $tipoTramite }}</p>
                </div>

                <div class="rounded-lg border border-border bg-bg-main p-3 space-y-0.5">
                    <p class="text-xs text-text-secondary">Origen</p>
                    <p class="text-sm font-semibold text-text-primary">{{ $origenRecoleccion }}</p>
                    @if($situacionRegulatoria && $situacionRegulatoria !== 'Proviene de colección foránea')
                        <p class="text-xs text-text-secondary">{{ $situacionRegulatoria }}</p>
                    @endif
                </div>

                @if($provincia)
                    <div class="rounded-lg border border-border bg-bg-main p-3 space-y-0.5">
                        <p class="text-xs text-text-secondary">Provincia</p>
                        <p class="text-sm font-semibold text-text-primary">{{ $provincia }}</p>
                        @if($localidad)
                            <p class="text-xs text-text-secondary">{{ $localidad }}</p>
                        @endif
                    </div>
                @endif

                <div class="rounded-lg border border-border bg-bg-main p-3 space-y-0.5">
                    <p class="text-xs text-text-secondary">Solicitante</p>
                    <p class="text-sm font-semibold text-text-primary">{{ auth()->user()->name }}</p>
                    @if($nombreEnDocumento && $nombreEnDocumento !== auth()->user()->name)
                        <p class="text-xs text-text-secondary">Doc: {{ $nombreEnDocumento }}</p>
                    @endif
                </div>

                @if(!empty($documentosCargados))
                    <div class="sm:col-span-2 rounded-lg border border-border bg-bg-main p-3 space-y-1.5">
                        <p class="text-xs text-text-secondary">Documentos adjuntados</p>
                        <ul class="space-y-1">
                            @foreach($documentosCargados as $nombre => $ruta)
                                <li class="flex items-center gap-2 text-sm text-text-primary">
                                    <flux:icon name="check-circle" class="size-4 text-success shrink-0" />
                                    {{ $nombre }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($matrizCargada && $estadoMatriz)
                    <div class="sm:col-span-2 rounded-lg border border-border bg-bg-main p-3 space-y-1.5">
                        <p class="text-xs text-text-secondary">Matriz de especies</p>
                        <div class="flex items-center gap-3 flex-wrap">
                            <p class="text-sm font-semibold text-text-primary">{{ $archivoMatrizNombre ?: 'Cargada' }}</p>
                            <x-gestionprestamosrecepciones::matrix-status-badge :estado="$estadoMatriz" />
                        </div>
                        <p class="text-xs text-text-secondary">{{ $totalRegistros }} registro(s)</p>
                    </div>
                @endif

            </div>
        </div>

        {{-- Declaración jurada --}}
        <div class="space-y-3 border-l-2 border-blue-navy/20 bg-[#F8FAFC] px-4 py-4">
            <flux:heading size="sm" level="3">Declaración del solicitante</flux:heading>
            <flux:checkbox
                wire:model="declaracionAceptada"
                label="Declaro bajo juramento que la información proporcionada y los documentos cargados son verídicos, y que cumplo con la normativa nacional e institucional vigente para el manejo de especímenes biológicos."
            />
            <flux:error name="declaracionAceptada" />
        </div>

        {{-- Documento institucional generado y firmado dentro de HubDigital --}}
        <div class="space-y-4 rounded-xl border border-bio-green/30 bg-bio-green/[0.04] p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-bio-green/10">
                        <flux:icon name="document-check" class="size-5 text-bio-green" />
                    </div>
                    <div>
                        <flux:heading size="sm" level="3">Solicitud oficial y firma electrónica</flux:heading>
                        <flux:text class="mt-1 text-xs text-text-secondary">
                            HubDigital ya generó el formulario institucional con los datos confirmados y la matriz Darwin Core.
                        </flux:text>
                    </div>
                </div>
                @if($solicitudFirmada)
                    <span class="inline-flex items-center gap-1.5 border-l-2 border-success bg-white px-2.5 py-1 text-xs font-semibold text-success">
                        <flux:icon name="shield-check" class="size-4" /> Firmada y validada
                    </span>
                @endif
            </div>

            <iframe
                title="Vista previa de la solicitud oficial"
                src="{{ route('depositos.solicitud.documento', ['id' => $solicitudId, 'original' => 1]) }}"
                class="h-96 w-full rounded-lg border border-border bg-white"
            ></iframe>

            @if(!$solicitudFirmada)
                <div
                    class="space-y-5 rounded-lg border border-blue-navy/15 bg-surface p-4 sm:p-5"
                    x-data="hubDigitalFirmador({
                        documentUrl: @js(route('depositos.solicitud.documento', ['id' => $solicitudId, 'original' => 1])),
                        uploadUrl: @js(route('depositos.solicitud.firmar', ['id' => $solicitudId])),
                        reason: 'Solicitud de {{ $tipoTramite }} de especímenes biológicos',
                        location: 'Quito, Ecuador'
                    })"
                >
                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:field>
                            <flux:label>Certificado electrónico (.p12 o .pfx)</flux:label>
                            <input x-ref="certificado" type="file" accept=".p12,.pfx,application/x-pkcs12" class="block w-full rounded-lg border border-border bg-white px-3 py-2 text-sm" />
                            <flux:description>El certificado se abre localmente en un proceso aislado de tu navegador.</flux:description>
                        </flux:field>
                        <flux:field>
                            <flux:label>Contraseña del certificado</flux:label>
                            <flux:input x-ref="clave" type="password" autocomplete="off" />
                            <flux:description>La contraseña nunca se transmite ni se almacena.</flux:description>
                        </flux:field>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <flux:button variant="primary" icon="lock-closed" x-on:click="firmar" x-bind:disabled="estado === 'procesando' || !declaracionAceptada">
                            <span x-show="estado !== 'procesando'">Firmar con Firmador HubDigital</span>
                            <span x-show="estado === 'procesando'" x-text="progreso || 'Procesando…'"></span>
                        </flux:button>
                        <a href="{{ route('depositos.solicitud.documento', ['id' => $solicitudId, 'original' => 1]) }}" target="_blank" class="text-sm font-medium text-science-blue hover:underline">Abrir PDF completo</a>
                    </div>
                    <p x-show="error" x-text="error" class="text-sm font-medium text-error" role="alert" aria-live="assertive"></p>
                    <div class="flex items-start gap-2 border-t border-blue-navy/10 pt-4 text-xs leading-5 text-text-secondary">
                        <flux:icon name="shield-check" class="mt-0.5 size-4 shrink-0 text-bio-green" />
                        <p><strong class="text-text-primary">Firma privada en tu navegador.</strong> El archivo P12 y la clave permanecen en este dispositivo. El servidor recibe únicamente el PDF firmado y comprueba el certificado, la cobertura total y la integridad visual.</p>
                    </div>
                </div>
            @else
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('depositos.solicitud.documento', ['id' => $solicitudId]) }}" target="_blank" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-border bg-surface px-4 py-2 text-sm font-semibold text-blue-navy hover:border-science-blue/40">
                        <flux:icon name="arrow-down-tray" class="size-4" /> Ver documento firmado
                    </a>
                    <p class="text-xs text-text-secondary">La copia firmada quedó sellada con huella SHA-256 en el expediente.</p>
                </div>
            @endif
            <flux:error name="solicitudFirmada" />
        </div>

    </div>

@endif
