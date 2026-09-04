<!DOCTYPE html>
<html lang="es">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="flex min-h-screen flex-col bg-white text-text-primary antialiased">
    <a href="#contenido-principal" class="sr-only z-[100] rounded-md bg-white px-4 py-3 font-semibold text-blue-navy focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:ring-2 focus:ring-science-blue">
        Saltar al contenido principal
    </a>

    <header x-data="{ abierto: false }" class="sticky top-0 z-50 border-b border-blue-navy/10 bg-white/95 backdrop-blur-md">
        <div class="mx-auto flex h-[4.75rem] max-w-7xl items-center justify-between gap-5 px-5 sm:px-8">
            <a href="{{ route('home') }}" class="group flex min-w-0 items-center gap-3 !text-blue-navy" aria-label="Inicio del Laboratorio de Invertebrados EPN">
                <img src="{{ asset('images/logo-epn.png') }}" alt="" class="h-12 w-auto shrink-0 object-contain" />
                <span class="h-10 w-px shrink-0 bg-blue-navy/20" aria-hidden="true"></span>
                <span class="min-w-0 leading-tight">
                    <span class="hidden text-[0.68rem] font-semibold uppercase tracking-[0.12em] text-text-secondary sm:block">Escuela Politécnica Nacional</span>
                    <span class="block truncate font-display text-base font-bold text-blue-navy sm:text-lg">Laboratorio de Invertebrados</span>
                </span>
            </a>

            <nav class="hidden h-full items-center gap-1 lg:flex" aria-label="Navegación principal">
                <a href="{{ route('home') }}" class="inline-flex h-full items-center border-b-2 px-3 text-sm font-semibold transition {{ request()->routeIs('home') ? 'border-science-blue !text-blue-navy' : 'border-transparent !text-text-secondary hover:!text-blue-navy' }}">Inicio</a>
                <a href="{{ route('home') }}#coleccion" class="inline-flex h-full items-center border-b-2 border-transparent px-3 text-sm font-semibold !text-text-secondary transition hover:!text-blue-navy">Colección</a>
                <a href="{{ route('portal.catalogo') }}" class="inline-flex h-full items-center border-b-2 px-3 text-sm font-semibold transition {{ request()->routeIs('portal.*') ? 'border-science-blue !text-blue-navy' : 'border-transparent !text-text-secondary hover:!text-blue-navy' }}">Catálogo</a>
                <a href="{{ route('depositos.portal') }}" class="inline-flex h-full items-center border-b-2 px-3 text-sm font-semibold transition {{ request()->routeIs('depositos.*') ? 'border-science-blue !text-blue-navy' : 'border-transparent !text-text-secondary hover:!text-blue-navy' }}">Depósitos</a>
                <a href="{{ route('home') }}#acerca" class="inline-flex h-full items-center border-b-2 border-transparent px-3 text-sm font-semibold !text-text-secondary transition hover:!text-blue-navy">Acerca del laboratorio</a>
            </nav>

            <div class="hidden shrink-0 lg:block">
                @auth
                    <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-md border border-blue-navy px-4 py-2 text-sm font-semibold !text-blue-navy transition hover:bg-blue-navy/[0.04] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue">
                        <svg viewBox="0 0 24 24" aria-hidden="true" class="size-4 fill-none stroke-current stroke-2"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></svg>
                        Mi cuenta
                    </a>
                @else
                    <a href="{{ route('login') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-md border border-blue-navy px-4 py-2 text-sm font-semibold !text-blue-navy transition hover:bg-blue-navy/[0.04] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue">
                        <svg viewBox="0 0 24 24" aria-hidden="true" class="size-4 fill-none stroke-current stroke-2"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></svg>
                        Iniciar sesión
                    </a>
                @endauth
            </div>

            <button
                type="button"
                @click="abierto = !abierto"
                :aria-expanded="abierto.toString()"
                aria-controls="menu-portal-movil"
                class="inline-flex size-11 shrink-0 items-center justify-center rounded-md border border-blue-navy/20 text-blue-navy transition hover:bg-blue-navy/[0.04] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue lg:hidden"
            >
                <span class="sr-only">Abrir menú principal</span>
                <svg x-show="!abierto" viewBox="0 0 24 24" aria-hidden="true" class="size-6 fill-none stroke-current stroke-2"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round" /></svg>
                <svg x-cloak x-show="abierto" viewBox="0 0 24 24" aria-hidden="true" class="size-6 fill-none stroke-current stroke-2"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round" /></svg>
            </button>
        </div>

        <nav
            id="menu-portal-movil"
            x-cloak
            x-show="abierto"
            x-transition.opacity.duration.150ms
            @click.outside="abierto = false"
            @keydown.escape.window="abierto = false"
            class="border-t border-blue-navy/10 bg-white px-5 py-4 shadow-lg lg:hidden"
            aria-label="Navegación principal móvil"
        >
            <div class="mx-auto grid max-w-7xl gap-1">
                <a href="{{ route('home') }}" @click="abierto = false" class="flex min-h-11 items-center border-l-2 border-transparent px-3 text-sm font-semibold !text-blue-navy hover:border-science-blue hover:bg-[#F5F8FC]">Inicio</a>
                <a href="{{ route('home') }}#coleccion" @click="abierto = false" class="flex min-h-11 items-center border-l-2 border-transparent px-3 text-sm font-semibold !text-blue-navy hover:border-science-blue hover:bg-[#F5F8FC]">Colección</a>
                <a href="{{ route('portal.catalogo') }}" @click="abierto = false" class="flex min-h-11 items-center border-l-2 border-transparent px-3 text-sm font-semibold !text-blue-navy hover:border-science-blue hover:bg-[#F5F8FC]">Catálogo</a>
                <a href="{{ route('depositos.portal') }}" @click="abierto = false" class="flex min-h-11 items-center border-l-2 border-transparent px-3 text-sm font-semibold !text-blue-navy hover:border-science-blue hover:bg-[#F5F8FC]">Depósitos</a>
                <a href="{{ route('home') }}#acerca" @click="abierto = false" class="flex min-h-11 items-center border-l-2 border-transparent px-3 text-sm font-semibold !text-blue-navy hover:border-science-blue hover:bg-[#F5F8FC]">Acerca del laboratorio</a>
                @auth
                    <a href="{{ route('dashboard') }}" wire:navigate class="mt-2 flex min-h-11 items-center justify-center rounded-md bg-blue-navy px-4 text-sm font-semibold !text-white">Mi cuenta</a>
                @else
                    <a href="{{ route('login') }}" wire:navigate class="mt-2 flex min-h-11 items-center justify-center rounded-md bg-blue-navy px-4 text-sm font-semibold !text-white">Iniciar sesión</a>
                @endauth
            </div>
        </nav>
    </header>

    <main id="contenido-principal" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="border-t-4 border-bio-green bg-[#102B4E] text-white/75">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 py-12 sm:px-8 lg:grid-cols-[1.15fr_0.7fr_0.9fr]">
            <div>
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/logo-epn.png') }}" alt="Escudo de la Escuela Politécnica Nacional" class="h-14 w-auto object-contain brightness-0 invert" />
                    <div class="border-l border-white/20 pl-3">
                        <p class="font-display text-lg font-semibold text-white">Laboratorio de Invertebrados</p>
                        <p class="mt-1 text-xs uppercase tracking-[0.12em] text-white/60">Escuela Politécnica Nacional</p>
                    </div>
                </div>
                <p class="mt-5 max-w-md text-sm leading-6">Hub Digital para la gestión, consulta y trazabilidad de la colección de invertebrados.</p>
            </div>

            <div>
                <h2 class="text-xs font-semibold uppercase tracking-[0.14em] text-white">Enlaces</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="{{ route('home') }}" class="!text-white/75 hover:!text-white">Inicio</a></li>
                    <li><a href="{{ route('portal.catalogo') }}" class="!text-white/75 hover:!text-white">Catálogo digital</a></li>
                    <li><a href="{{ route('depositos.portal') }}" class="!text-white/75 hover:!text-white">Depósitos biológicos</a></li>
                    <li><a href="{{ route('home') }}#acerca" class="!text-white/75 hover:!text-white">Acerca del laboratorio</a></li>
                </ul>
            </div>

            <div>
                <h2 class="text-xs font-semibold uppercase tracking-[0.14em] text-white">Ubicación institucional</h2>
                <p class="mt-4 text-sm leading-6">Departamento de Biología<br />Facultad de Ciencias<br />Escuela Politécnica Nacional<br />Quito, Ecuador</p>
            </div>
        </div>
        <div class="border-t border-white/10">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-5 text-xs text-white/55 sm:px-8 md:flex-row md:items-center md:justify-between">
                <p>© {{ now()->year }} Escuela Politécnica Nacional. Todos los derechos reservados.</p>
                <p>Los registros públicos respetan las políticas de divulgación y protección de datos sensibles de la colección.</p>
            </div>
        </div>
    </footer>

    @livewire(\Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget::class)

    @fluxScripts
</body>
</html>
