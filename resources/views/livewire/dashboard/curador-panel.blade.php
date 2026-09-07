<div class="hub-workspace flex h-full w-full flex-1 flex-col gap-7 p-4 sm:p-6">

    <header class="hub-page-header">
        <div>
            <p class="hub-page-kicker">Gestión de colección · EPN</p>
            <h1 class="mt-1 hub-page-title">Panel del curador</h1>
            <p class="mt-2 text-sm text-text-secondary">Prioriza la recepción, la trazabilidad y el cierre documental de la colección.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('prestamos.curador.depositos', ['vista' => 'actas']) }}" wire:navigate class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-science-blue px-4 py-2 text-sm font-semibold !text-white shadow-sm transition hover:bg-[#005a91] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2">
                <flux:icon name="pencil-square" class="size-4" /> Actas pendientes
            </a>
            <a href="{{ route('prestamos.curador.depositos') }}" wire:navigate class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-4 py-2 text-sm font-semibold text-blue-navy transition hover:border-science-blue/45 hover:bg-bg-main">
                <flux:icon name="inbox-arrow-down" class="size-4" /> Revisar ingresos
            </a>
        </div>
    </header>

    {{-- ── La colección ─────────────────────────────────────────────
         Gestión de información taxonómica + Divulgación. Va primero
         porque es la magnitud real del acervo bajo custodia. --}}
    <section class="flex flex-col gap-4">
        <div class="flex items-end justify-between gap-4">
            <div><h2 class="hub-section-title">La colección</h2><p class="mt-1 text-sm text-text-secondary">Acervo bajo custodia y calidad de su descripción taxonómica.</p></div>
            <a href="{{ route('inventario.taxonomia.especimenes') }}" wire:navigate class="hidden text-sm font-semibold text-science-blue hover:underline sm:inline">Abrir catálogo</a>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
            {{-- Número protagonista. Va en la tipografía de interfaz y no en la
                 serif de los títulos: una cifra grande en serif se lee como
                 adorno. Uno solo por pantalla. --}}
            <a href="{{ route('inventario.taxonomia.especimenes') }}" wire:navigate
                class="flex flex-col justify-center rounded-lg border border-border bg-surface p-6 shadow-sm transition hover:border-science-blue/40 hover:shadow">
                <p class="text-sm text-text-secondary">Especímenes bajo custodia</p>
                <p class="mt-1 text-5xl font-semibold leading-none text-blue-navy">
                    {{ number_format($colEspecimenes, 0, ',', '.') }}
                </p>
                <p class="mt-2 text-xs text-text-secondary">
                    En {{ number_format($colLocalidades, 0, ',', '.') }} localidades registradas
                </p>
            </a>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-tile
                    label="Taxones" :value="$colTaxones"
                    icon="rectangle-stack" tone="blue"
                    :href="route('inventario.taxonomia.taxones')" />
                <x-stat-tile
                    label="Localidades" :value="$colLocalidades"
                    icon="map" tone="blue"
                    :href="route('inventario.taxonomia.localidades')" />
                <x-stat-tile
                    label="Publicados en el portal" :value="$divPublicados"
                    icon="globe-alt" tone="green"
                    hint="Visibles para el público"
                    :href="route('divulgacion.index')" />
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-bar-chart
                titulo="Familias mejor representadas"
                :subtitulo="'Las 8 con más registros, de '.number_format($colFamilias, 0, ',', '.').' familias en la colección'"
                :filas="$graficoFamilias" />
            <x-bar-chart
                titulo="Nivel de determinación"
                subtitulo="Hasta qué rango está identificado cada espécimen"
                :filas="$graficoDeterminacion" />
        </div>
    </section>

    {{-- ── Recepción y depósitos ──────────────────────────────────── --}}
    <section class="flex flex-col gap-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="font-display text-lg font-semibold text-blue-navy">Recepción y depósitos</h2>
                <p class="mt-1 text-sm text-text-secondary">Análisis operativo del ciclo documental, la entrega física y el cierre del acta.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:select wire:model.live="periodoAnalisis" aria-label="Período del análisis" class="w-40">
                    <flux:select.option value="6">Últimos 6 meses</flux:select.option>
                    <flux:select.option value="12">Últimos 12 meses</flux:select.option>
                    <flux:select.option value="24">Últimos 24 meses</flux:select.option>
                </flux:select>
                <flux:button wire:click="descargarReporteDepositos" variant="ghost" icon="arrow-down-tray">Descargar reporte</flux:button>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-tile
                label="Depósitos por revisar" :value="$depPorRevisar"
                icon="clipboard-document-check"
                :tone="$depPorRevisar > 0 ? 'warning' : 'blue'"
                :href="route('prestamos.curador.depositos')" />
            <x-stat-tile
                label="Solicitudes recibidas" :value="$depEnviadas"
                icon="inbox-arrow-down" tone="blue"
                :href="route('prestamos.curador.depositos')" />
            <x-stat-tile
                label="Lotes recibidos físicamente" :value="$depLotesRecibidos"
                icon="archive-box-arrow-down" tone="green"
                :href="route('prestamos.curador.depositos')" />
            <x-stat-tile
                label="Registros en matrices" :value="$depRegistrosMatriz"
                icon="table-cells" tone="blue"
                hint="Filas estructuradas de las matrices" />
        </div>

        <div class="grid gap-4 pt-1 lg:grid-cols-2">
            <x-bar-chart
                titulo="Tendencia de solicitudes"
                :subtitulo="'Depósitos y donaciones enviados durante '.$periodoAnaliticoEtiqueta"
                :filas="$graficoDepositosPorMes" />
            <x-bar-chart
                titulo="Estado de los depósitos"
                :subtitulo="'Distribución documental durante '.$periodoAnaliticoEtiqueta"
                :filas="$graficoEstadosDepositos" />
        </div>

        <div class="grid gap-4 lg:grid-cols-[1.05fr_.95fr]">
            <article class="rounded-xl border border-border bg-surface p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div><h3 class="font-display text-lg font-semibold text-blue-navy">Embudo de recepción</h3><p class="mt-1 text-sm text-text-secondary">{{ $periodoAnaliticoEtiqueta }} · del registro al acta firmada.</p></div>
                    <span class="rounded-full bg-bio-green/10 px-2.5 py-1 text-xs font-semibold text-bio-green">Operación museológica</span>
                </div>
                <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ([['Solicitudes', $indicadoresDepositos['total'], 'inbox-arrow-down'], ['Aprobadas', $indicadoresDepositos['aprobadas'], 'check-circle'], ['Constatadas', $indicadoresDepositos['constatadas'], 'clipboard-document-check'], ['Con observaciones', $indicadoresDepositos['observaciones'], 'exclamation-triangle'], ['Actas pendientes', $indicadoresDepositos['actasPendientes'], 'pencil-square'], ['Actas firmadas', $indicadoresDepositos['actasFirmadas'], 'shield-check']] as [$etiqueta, $valor, $icono])
                        <div class="rounded-lg border border-border bg-bg-main/60 p-3"><flux:icon name="{{ $icono }}" class="size-4 text-bio-green" /><dd class="mt-2 font-display text-2xl font-semibold text-blue-navy">{{ number_format($valor, 0, ',', '.') }}</dd><dt class="mt-1 text-xs leading-5 text-text-secondary">{{ $etiqueta }}</dt></div>
                    @endforeach
                </dl>
                <div class="mt-5 grid gap-3 border-t border-border pt-4 sm:grid-cols-2">
                    <div><p class="text-xs uppercase tracking-wide text-text-secondary">Tiempo medio documental</p><p class="mt-1 text-lg font-semibold text-blue-navy">{{ number_format($indicadoresDepositos['diasRevision'], 1, ',', '.') }} días</p><p class="text-xs text-text-secondary">Creación del expediente a aprobación</p></div>
                    <div><p class="text-xs uppercase tracking-wide text-text-secondary">Tiempo medio de constatación</p><p class="mt-1 text-lg font-semibold text-blue-navy">{{ number_format($indicadoresDepositos['diasConstatacion'], 1, ',', '.') }} días</p><p class="text-xs text-text-secondary">Apertura a constatación</p></div>
                </div>
            </article>

            <x-bar-chart titulo="Estado de recepción física" :subtitulo="$periodoAnaliticoEtiqueta.' · control de entrega y cadena de custodia'" :filas="$graficoRecepciones" />
        </div>

        <section class="overflow-hidden rounded-xl border border-border bg-surface shadow-sm" aria-labelledby="cola-curatorial">
            <div class="flex flex-col gap-3 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between"><div><h3 id="cola-curatorial" class="font-display text-lg font-semibold text-blue-navy">Cola de acción curatorial</h3><p class="mt-1 text-sm text-text-secondary">Actas de lotes recibidos y decisiones documentales que requieren atención.</p></div><a href="{{ route('prestamos.curador.depositos', ['vista' => 'actas']) }}" wire:navigate class="text-sm font-semibold text-science-blue hover:underline">Ver todas las actas</a></div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-bg-main/70 text-left text-xs font-semibold uppercase tracking-wide text-text-secondary"><tr><th class="px-5 py-3">Expediente</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3">Fecha</th><th class="px-5 py-3 text-right">Acción</th></tr></thead><tbody class="divide-y divide-border">
                @forelse ($colaCuratorial as $fila)
                    <tr class="hover:bg-bg-main/40"><td class="px-5 py-4"><p class="font-mono text-xs text-text-secondary">{{ $fila['numero'] }}</p><p class="mt-1 font-medium text-text-primary">{{ $fila['detalle'] }}</p></td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $fila['prioridad'] === 'acta' ? 'bg-amber-50 text-amber-800' : 'bg-science-blue/10 text-science-blue' }}">{{ $fila['estado'] }}</span></td><td class="whitespace-nowrap px-5 py-4 text-xs text-text-secondary">{{ $fila['fecha'] }}</td><td class="px-5 py-4 text-right"><a href="{{ $fila['ruta'] }}" wire:navigate class="inline-flex min-h-9 items-center rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-blue-navy hover:border-science-blue/45">{{ $fila['accion'] }}</a></td></tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-text-secondary">No hay acciones curatoriales pendientes en este período.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </section>

    {{-- ── Préstamos ──────────────────────────────────────────────── --}}
    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-blue-navy">Préstamos</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-tile
                label="Solicitudes pendientes" :value="$statSolicitudesPendientes"
                icon="inbox"
                :tone="$statSolicitudesPendientes > 0 ? 'warning' : 'blue'"
                :href="route('prestamos.curador.solicitudes')" />
            <x-stat-tile
                label="Préstamos activos" :value="$prestActivos"
                icon="arrow-path" tone="green"
                :href="route('prestamos.curador.prestamos')" />
            <x-stat-tile
                label="Préstamos vencidos" :value="$prestVencidos"
                icon="exclamation-circle"
                :tone="$prestVencidos > 0 ? 'error' : 'blue'"
                :href="route('prestamos.curador.prestamos')" />
            <x-stat-tile
                label="Actas por validar" :value="$statActasPorValidar"
                icon="document-check"
                :tone="$statActasPorValidar > 0 ? 'warning' : 'blue'"
                :href="route('prestamos.curador.actas')" />
        </div>
    </section>

    {{-- ── Seguimiento físico ─────────────────────────────────────── --}}
    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-blue-navy">Seguimiento físico</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-tile
                label="Alertas activas" :value="$statAlertasActivas"
                icon="exclamation-triangle"
                :tone="$statAlertasActivas > 0 ? 'error' : 'blue'"
                :href="route('inventario.alertas')" />
            <x-stat-tile
                label="Cajas fuera de su lugar" :value="$statCajasFueraDeLugar"
                icon="map-pin"
                :tone="$statCajasFueraDeLugar > 0 ? 'warning' : 'blue'"
                :href="route('inventario.cajas')" />
            <x-stat-tile
                label="Total de cajas" :value="$statCajasTotal"
                icon="archive-box" tone="blue"
                :href="route('inventario.cajas')" />
            <x-stat-tile
                label="Movimientos registrados" :value="$fisMovimientos"
                icon="clock" tone="navy"
                hint="Trazabilidad completa"
                :href="route('inventario.trazabilidad')" />
        </div>
    </section>

</div>
