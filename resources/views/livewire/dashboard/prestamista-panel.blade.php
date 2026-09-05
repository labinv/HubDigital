<div class="hub-workspace flex h-full w-full flex-1 flex-col gap-6 p-4 sm:p-6">

    {{-- Invitación a activar el rol complementario. Vive aquí y no en el layout:
         es contenido del panel, no del armazón, y antes salía en todas las pantallas. --}}
    <x-banner-activar-rol />

    <header class="hub-page-header">
        <div class="flex flex-col gap-1">
            <p class="hub-page-kicker">Consulta y custodia de especímenes</p>
            <h1 class="hub-page-title">Mis préstamos</h1>
            <p class="text-sm text-text-secondary">Bienvenido, {{ auth()->user()->name }}</p>
        </div>
        <flux:button href="{{ route('prestamos.investigador.solicitud.crear') }}" wire:navigate variant="primary" icon="plus">
            Nueva solicitud
        </flux:button>
    </header>

    {{-- ── Mis solicitudes ────────────────────────────────────────── --}}
    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-blue-navy">Mis solicitudes</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-tile
                label="Enviadas" :value="$statTotal"
                icon="document-text" tone="blue"
                :href="route('prestamos.investigador.mis-solicitudes')" />
            <x-stat-tile
                label="Aprobadas" :value="$statAprobadas"
                icon="check-circle" tone="green"
                :href="route('prestamos.investigador.mis-solicitudes')" />
            <x-stat-tile
                label="En revisión" :value="$statPendientes"
                icon="clock"
                :tone="$statPendientes > 0 ? 'warning' : 'blue'"
                :href="route('prestamos.investigador.mis-solicitudes')" />
        </div>
    </section>

    {{-- ── Material bajo mi custodia ──────────────────────────────── --}}
    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-blue-navy">Material bajo mi custodia</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-tile
                label="Especímenes en mi poder" :value="$especimenesEnCustodia"
                icon="bug-ant" tone="navy"
                hint="De préstamos aún abiertos"
                :href="route('prestamos.investigador.mis-prestamos')" />
            <x-stat-tile
                label="Préstamos activos" :value="$prestActivos"
                icon="arrow-path" tone="green"
                :href="route('prestamos.investigador.mis-prestamos')" />
            <x-stat-tile
                label="Vencidos" :value="$prestVencidos"
                icon="exclamation-circle"
                :tone="$prestVencidos > 0 ? 'error' : 'blue'"
                :hint="$prestVencidos > 0 ? 'Requieren devolución' : null"
                :href="route('prestamos.investigador.mis-prestamos')" />
            <x-stat-tile
                label="Devueltos" :value="$prestCerrados"
                icon="archive-box-arrow-down" tone="blue"
                :href="route('prestamos.investigador.mis-prestamos')" />
        </div>
    </section>

    {{-- ── Accesos rápidos ────────────────────────────────────────── --}}
    <section class="hub-panel p-6">
        <h2 class="font-display text-lg font-semibold text-blue-navy">Accesos rápidos</h2>
        <div class="mt-4 flex flex-wrap gap-3">
            <flux:button href="{{ route('prestamos.investigador.solicitud.crear') }}" wire:navigate variant="primary" icon="plus">
                Nueva solicitud
            </flux:button>
            <flux:button href="{{ route('prestamos.investigador.mis-solicitudes') }}" wire:navigate variant="ghost" icon="clipboard-document-list">
                Ver mis solicitudes
            </flux:button>
            <flux:button href="{{ route('prestamos.investigador.mis-actas') }}" wire:navigate variant="ghost" icon="document-check">
                Mis actas
            </flux:button>
            <flux:button href="{{ route('portal.catalogo') }}" variant="ghost" icon="magnifying-glass">
                Explorar el catálogo
            </flux:button>
        </div>
    </section>

</div>
