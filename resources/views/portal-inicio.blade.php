@component('layouts.portal', ['title' => 'Laboratorio de Invertebrados · Escuela Politécnica Nacional'])
    <div class="overflow-hidden bg-white text-text-primary">
        <section class="relative isolate border-b border-blue-navy/10 bg-white" aria-labelledby="titulo-portada">
            <div class="mx-auto grid max-w-[96rem] lg:min-h-[39rem] lg:grid-cols-[0.9fr_1.1fr]">
                <div class="relative z-10 flex items-center px-5 py-12 sm:px-8 sm:py-16 lg:px-12 lg:py-20 xl:pl-[max(3rem,calc((100vw-80rem)/2))]">
                    <div class="max-w-2xl">
                        <h1 id="titulo-portada" class="font-display text-[2.65rem] font-bold leading-[1.04] tracking-[-0.035em] text-blue-navy sm:text-6xl lg:text-[4.25rem]">
                            Conocimiento que preserva la biodiversidad
                        </h1>
                        <p class="mt-6 max-w-xl text-base leading-7 text-text-secondary sm:text-lg sm:leading-8">
                            Conservamos, documentamos y ponemos en contexto científico el acervo de invertebrados del Museo de Historia Natural Gustavo Orcés V. de la Escuela Politécnica Nacional.
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a
                                href="{{ route('portal.catalogo') }}"
                                class="inline-flex min-h-12 items-center justify-center gap-3 rounded-md bg-science-blue px-5 py-3 text-sm font-semibold !text-white shadow-sm transition hover:bg-[#1266b8] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2"
                            >
                                Explorar el catálogo
                                <svg viewBox="0 0 24 24" aria-hidden="true" class="size-4 fill-none stroke-current stroke-2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </a>
                            <a
                                href="{{ route('depositos.portal') }}"
                                class="inline-flex min-h-12 items-center justify-center gap-3 rounded-md border border-bio-green bg-white px-5 py-3 text-sm font-semibold !text-bio-green transition hover:bg-bio-green/[0.05] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bio-green focus-visible:ring-offset-2"
                            >
                                Realizar un depósito
                                <svg viewBox="0 0 24 24" aria-hidden="true" class="size-4 fill-none stroke-current stroke-2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </a>
                        </div>
                    </div>
                </div>

                <figure class="relative min-h-80 overflow-hidden sm:min-h-[28rem] lg:min-h-full">
                    <img
                        src="{{ asset('images/portal-laboratorio-hero.jpg') }}"
                        alt="Gaveta entomológica del Laboratorio de Invertebrados durante una tarea curatorial"
                        width="1536"
                        height="1024"
                        class="absolute inset-0 size-full object-cover object-center"
                        fetchpriority="high"
                        decoding="async"
                    />
                    <div class="absolute inset-y-0 left-0 hidden w-36 bg-gradient-to-r from-white to-transparent lg:block" aria-hidden="true"></div>
                    <figcaption class="absolute bottom-4 right-4 max-w-[15rem] border-l-2 border-bio-green bg-white/95 px-3 py-2 text-xs leading-5 text-blue-navy shadow-sm backdrop-blur-sm">
                        Curaduría y documentación de especímenes de la colección.
                    </figcaption>
                </figure>
            </div>
        </section>

        <section id="coleccion" class="scroll-mt-24 bg-white" aria-labelledby="titulo-coleccion">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 py-16 sm:px-8 lg:grid-cols-[0.78fr_1.22fr] lg:gap-20 lg:py-24">
                <div>
                    <div class="h-1 w-14 bg-bio-green" aria-hidden="true"></div>
                    <h2 id="titulo-coleccion" class="mt-6 font-display text-3xl font-bold tracking-[-0.02em] text-blue-navy sm:text-4xl">
                        Una colección para investigar, enseñar y conservar
                    </h2>
                </div>
                <div class="grid gap-8 text-base leading-8 text-text-secondary sm:grid-cols-2">
                    <p>
                        El Laboratorio de Invertebrados preserva y documenta material biológico para respaldar la investigación sobre la diversidad del Ecuador y mantener su información asociada.
                    </p>
                    <p>
                        Cada registro conserva la relación entre el espécimen, su identificación taxonómica, procedencia y trazabilidad curatorial para su consulta responsable.
                    </p>
                </div>
            </div>
        </section>

        <section class="border-y border-blue-navy/10 bg-[#F5F8FC]" aria-labelledby="titulo-servicios">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
                <div class="grid gap-12 lg:grid-cols-[0.42fr_1fr] lg:gap-20">
                    <div>
                        <h2 id="titulo-servicios" class="font-display text-3xl font-bold tracking-[-0.02em] text-blue-navy sm:text-4xl">Explora y participa</h2>
                        <p class="mt-4 max-w-sm leading-7 text-text-secondary">Recursos y servicios para investigar, consultar y colaborar con el patrimonio biológico.</p>
                    </div>

                    <div class="divide-y divide-blue-navy/15 border-y border-blue-navy/15">
                        <article class="grid gap-5 py-8 sm:grid-cols-[4.5rem_1fr_auto] sm:items-center">
                            <span class="font-display text-5xl font-bold text-bio-green/80" aria-hidden="true">01</span>
                            <div>
                                <h3 class="font-display text-2xl font-semibold text-blue-navy">Colección científica</h3>
                                <p class="mt-2 max-w-2xl leading-7 text-text-secondary">Preservación, documentación y trazabilidad de especímenes mediante prácticas curatoriales y estándares científicos.</p>
                            </div>
                            <a href="#acerca" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold !text-science-blue hover:underline sm:justify-self-end">Conocer el laboratorio <span aria-hidden="true">→</span></a>
                        </article>

                        <article id="catalogo" class="scroll-mt-24 grid gap-5 py-8 sm:grid-cols-[4.5rem_1fr_auto] sm:items-center">
                            <span class="font-display text-5xl font-bold text-bio-green/80" aria-hidden="true">02</span>
                            <div>
                                <h3 class="font-display text-2xl font-semibold text-blue-navy">Catálogo digital</h3>
                                <p class="mt-2 max-w-2xl leading-7 text-text-secondary">Consulta en línea los registros taxonómicos que la colección ha preparado para divulgación pública.</p>
                            </div>
                            <a href="{{ route('portal.catalogo') }}" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold !text-science-blue hover:underline sm:justify-self-end">Explorar registros <span aria-hidden="true">→</span></a>
                        </article>

                        <article id="depositos" class="scroll-mt-24 grid gap-5 py-8 sm:grid-cols-[4.5rem_1fr_auto] sm:items-center">
                            <span class="font-display text-5xl font-bold text-bio-green/80" aria-hidden="true">03</span>
                            <div>
                                <h3 class="font-display text-2xl font-semibold text-blue-navy">Depósitos biológicos</h3>
                                <p class="mt-2 max-w-2xl leading-7 text-text-secondary">Trámite guiado para registrar un depósito temporal o una donación de material biológico y dar seguimiento a cada etapa.</p>
                            </div>
                            <a href="{{ route('depositos.portal') }}" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold !text-science-blue hover:underline sm:justify-self-end">Conocer el proceso <span aria-hidden="true">→</span></a>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-blue-navy text-white" aria-labelledby="titulo-proceso">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-20">
                <div class="flex flex-col gap-6 border-b border-white/20 pb-9 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="h-1 w-14 bg-bio-green" aria-hidden="true"></div>
                        <h2 id="titulo-proceso" class="mt-5 font-display text-3xl font-bold tracking-[-0.02em] sm:text-4xl">Depositar material biológico</h2>
                        <p class="mt-3 max-w-2xl leading-7 text-white/75">Un flujo documentado para asegurar la procedencia, calidad y trazabilidad del material.</p>
                    </div>
                    <a href="{{ route('depositos.portal') }}" class="inline-flex min-h-12 shrink-0 items-center justify-center gap-3 rounded-md border border-bio-green bg-transparent px-5 py-3 text-sm font-semibold !text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">Ir a Depósitos <span class="text-bio-green" aria-hidden="true">→</span></a>
                </div>

                <ol class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach([
                        ['01', 'Prepara', 'Revisa los requisitos y reúne los documentos aplicables.'],
                        ['02', 'Registra', 'Completa el formulario en línea y adjunta la documentación.'],
                        ['03', 'Revisión curatorial', 'El equipo evalúa la información y comunica el resultado.'],
                        ['04', 'Entrega y acta', 'La EPN constata el lote y formaliza el ingreso mediante el acta.'],
                    ] as [$numero, $titulo, $descripcion])
                        <li class="border-l border-white/20 pl-5">
                            <span class="font-display text-4xl font-semibold text-bio-green">{{ $numero }}</span>
                            <h3 class="mt-4 font-display text-xl font-semibold text-white">{{ $titulo }}</h3>
                            <p class="mt-2 text-sm leading-6 text-white/70">{{ $descripcion }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section class="bg-white" aria-labelledby="titulo-datos">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 py-16 sm:px-8 lg:grid-cols-[1fr_0.78fr] lg:items-center lg:gap-20 lg:py-24">
                <div>
                    <h2 id="titulo-datos" class="font-display text-3xl font-bold tracking-[-0.02em] text-blue-navy sm:text-4xl">Datos con contexto científico</h2>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-text-secondary">
                        El sistema estructura la información biológica con términos Darwin Core, valida nombres científicos con catálogos taxonómicos controlados y prepara datos para procesos de calidad e interoperabilidad con GBIF.
                    </p>
                    <a href="{{ route('portal.catalogo') }}" class="mt-7 inline-flex min-h-12 items-center justify-center gap-3 rounded-md border border-blue-navy px-5 py-3 text-sm font-semibold !text-blue-navy transition hover:bg-blue-navy/[0.04] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2">Consultar el catálogo <span aria-hidden="true">→</span></a>
                </div>
                <div class="relative min-h-64 overflow-hidden border-l border-blue-navy/15 pl-8 sm:pl-12" aria-hidden="true">
                    <svg viewBox="0 0 420 260" class="h-full w-full max-w-lg fill-none stroke-blue-navy/35" stroke-width="1.4">
                        <path d="M220 38c-24 0-43 15-43 38v111c0 27 19 45 43 45s43-18 43-45V76c0-23-19-38-43-38Z" />
                        <path d="M194 51c-21-28-36-24-54-36M246 51c21-28 36-24 54-36M178 84l-62-31M177 118l-71 4M177 151l-65 35M263 84l62-31M263 118l71 4M263 151l65 35M220 40V231M178 95h85M178 133h85M178 171h85" />
                        <rect x="31" y="86" width="100" height="76" rx="3" />
                        <path d="M47 106h66M47 121h48M47 136h58" />
                        <path d="M322 204h54v36h-54zM330 214h38M330 225h27" />
                    </svg>
                </div>
            </div>
        </section>

        <section id="acerca" class="scroll-mt-24 border-t border-blue-navy/10 bg-[#F5F8FC]" aria-labelledby="titulo-acerca">
            <div class="mx-auto grid max-w-7xl gap-8 px-5 py-14 sm:px-8 lg:grid-cols-[1fr_auto] lg:items-center lg:py-16">
                <div>
                    <h2 id="titulo-acerca" class="font-display text-3xl font-bold text-blue-navy">Laboratorio de Invertebrados</h2>
                    <p class="mt-4 flex max-w-2xl items-start gap-3 leading-7 text-text-secondary">
                        <svg viewBox="0 0 24 24" aria-hidden="true" class="mt-1 size-5 shrink-0 fill-none stroke-bio-green stroke-2"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" /></svg>
                        <span>Departamento de Biología · Facultad de Ciencias · Escuela Politécnica Nacional · Quito, Ecuador</span>
                    </p>
                </div>
                <a href="https://biologia.epn.edu.ec/index.php/secciones/entomologia/coleccion" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center justify-center gap-3 rounded-md bg-science-blue px-5 py-3 text-sm font-semibold !text-white transition hover:bg-[#1266b8] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2">Información institucional <span aria-hidden="true">↗</span></a>
            </div>
        </section>
    </div>
@endcomponent
