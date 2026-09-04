@component('layouts.portal', ['title' => 'Laboratorio de Invertebrados · Escuela Politécnica Nacional'])
    <div class="overflow-hidden bg-white text-text-primary">
        <section class="relative isolate border-b border-blue-navy/10 bg-white" aria-labelledby="titulo-portada">
            <div class="mx-auto grid max-w-[96rem] lg:min-h-[42rem] lg:grid-cols-[0.86fr_1.14fr]">
                <div class="relative z-10 flex items-center px-5 py-14 sm:px-8 sm:py-18 lg:px-12 lg:py-24 xl:pl-[max(3rem,calc((100vw-80rem)/2))]">
                    <div class="max-w-2xl">
                        <h1 id="titulo-portada" class="font-display text-[2.65rem] font-bold leading-[1.04] tracking-[-0.035em] text-blue-navy sm:text-6xl lg:text-[4.35rem]">
                            Ciencia, colecciones y biodiversidad del Ecuador
                        </h1>
                        <p class="mt-6 max-w-xl text-base leading-7 text-text-secondary sm:text-lg sm:leading-8">
                            El Laboratorio de Invertebrados de la Escuela Politécnica Nacional conserva, estudia y conecta con la sociedad el patrimonio biológico que custodia el Museo de Historia Natural Gustavo Orcés V.
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
                                href="#colecciones"
                                class="inline-flex min-h-12 items-center justify-center gap-3 rounded-md border border-blue-navy/40 bg-white px-5 py-3 text-sm font-semibold !text-blue-navy transition hover:border-blue-navy hover:bg-blue-navy/[0.04] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2"
                            >
                                Conocer las colecciones
                                <svg viewBox="0 0 24 24" aria-hidden="true" class="size-4 fill-none stroke-current stroke-2"><path d="m7 10 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </a>
                        </div>
                    </div>
                </div>

                <figure class="relative min-h-[23rem] overflow-hidden sm:min-h-[30rem] lg:min-h-full">
                    <img
                        src="{{ asset('images/portal-laboratorio-hero.jpg') }}"
                        alt="Gaveta entomológica durante una tarea de curaduría científica"
                        width="1536"
                        height="1024"
                        class="absolute inset-0 size-full object-cover object-center"
                        fetchpriority="high"
                        decoding="async"
                    />
                    <div class="absolute inset-y-0 left-0 hidden w-40 bg-gradient-to-r from-white to-transparent lg:block" aria-hidden="true"></div>
                    <figcaption class="absolute bottom-4 right-4 max-w-[17rem] border-l-2 border-bio-green bg-white/95 px-3 py-2 text-xs leading-5 text-blue-navy shadow-sm backdrop-blur-sm">
                        Conservación y documentación de ejemplares de la colección científica.
                    </figcaption>
                </figure>
            </div>
        </section>

        <section class="border-b border-blue-navy/10 bg-[#F5F8FC]" aria-label="Propósitos del laboratorio">
            <div class="mx-auto grid max-w-7xl grid-cols-2 px-5 sm:px-8 lg:grid-cols-4">
                @foreach([
                    ['Preservar', 'Patrimonio biológico'],
                    ['Documentar', 'Datos verificables'],
                    ['Investigar', 'Diversidad del Ecuador'],
                    ['Compartir', 'Conocimiento científico'],
                ] as [$accion, $resultado])
                    <div class="border-blue-navy/10 py-6 odd:border-r even:pl-6 lg:border-r lg:px-7 lg:first:pl-0 lg:last:border-r-0">
                        <p class="font-display text-lg font-semibold text-blue-navy">{{ $accion }}</p>
                        <p class="mt-1 text-xs uppercase tracking-[0.1em] text-text-secondary">{{ $resultado }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section id="colecciones" class="scroll-mt-28 bg-white" aria-labelledby="titulo-colecciones">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
                <div class="grid gap-10 lg:grid-cols-[0.72fr_1.28fr] lg:gap-20">
                    <div>
                        <div class="h-1 w-14 bg-bio-green" aria-hidden="true"></div>
                        <h2 id="titulo-colecciones" class="mt-6 font-display text-3xl font-bold tracking-[-0.02em] text-blue-navy sm:text-4xl">
                            Una infraestructura científica para la biodiversidad
                        </h2>
                        <p class="mt-5 max-w-md leading-8 text-text-secondary">
                            Las colecciones vinculan cada ejemplar con su identificación, procedencia, permisos y trayectoria curatorial para que pueda volver a ser estudiado.
                        </p>
                    </div>

                    <div class="divide-y divide-blue-navy/15 border-y border-blue-navy/15">
                        <article class="grid gap-4 py-7 sm:grid-cols-[3.5rem_1fr] sm:gap-6">
                            <span class="font-display text-3xl font-semibold text-bio-green" aria-hidden="true">01</span>
                            <div>
                                <h3 class="font-display text-2xl font-semibold text-blue-navy">Colecciones de referencia</h3>
                                <p class="mt-2 leading-7 text-text-secondary">Material entomológico y de otros grupos de invertebrados preservado con información sobre su origen e identificación taxonómica.</p>
                            </div>
                        </article>
                        <article class="grid gap-4 py-7 sm:grid-cols-[3.5rem_1fr] sm:gap-6">
                            <span class="font-display text-3xl font-semibold text-bio-green" aria-hidden="true">02</span>
                            <div>
                                <h3 class="font-display text-2xl font-semibold text-blue-navy">Gestión y curaduría</h3>
                                <p class="mt-2 leading-7 text-text-secondary">Ingreso, catalogación, conservación preventiva, revisión de datos y seguimiento de movimientos bajo responsabilidad institucional.</p>
                            </div>
                        </article>
                        <article class="grid gap-4 py-7 sm:grid-cols-[3.5rem_1fr] sm:gap-6">
                            <span class="font-display text-3xl font-semibold text-bio-green" aria-hidden="true">03</span>
                            <div>
                                <h3 class="font-display text-2xl font-semibold text-blue-navy">Acceso responsable</h3>
                                <p class="mt-2 leading-7 text-text-secondary">Consulta de registros públicos y atención de solicitudes científicas, respetando restricciones sobre localidades y datos sensibles.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section id="catalogo" class="scroll-mt-28 bg-blue-navy text-white" aria-labelledby="titulo-catalogo">
            <div class="mx-auto grid max-w-7xl gap-12 px-5 py-16 sm:px-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:gap-20 lg:py-24">
                <div>
                    <h2 id="titulo-catalogo" class="font-display text-3xl font-bold tracking-[-0.02em] sm:text-4xl">Datos abiertos con contexto científico</h2>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-white/75">
                        El catálogo reúne información taxonómica y geográfica preparada para consulta. Los registros se estructuran con términos Darwin Core y se contrastan con fuentes taxonómicas como GBIF para favorecer su calidad e interoperabilidad.
                    </p>
                    <a href="{{ route('portal.catalogo') }}" class="mt-8 inline-flex min-h-12 items-center justify-center gap-3 rounded-md bg-white px-5 py-3 text-sm font-semibold !text-blue-navy transition hover:bg-white/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-blue-navy">
                        Consultar el catálogo digital
                        <svg viewBox="0 0 24 24" aria-hidden="true" class="size-4 fill-none stroke-current stroke-2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </a>
                </div>

                <dl class="border-y border-white/20">
                    <div class="grid grid-cols-[8rem_1fr] gap-5 border-b border-white/20 py-5">
                        <dt class="font-semibold text-[#9BD7A5]">Darwin Core</dt>
                        <dd class="text-sm leading-6 text-white/70">Vocabulario común para describir y compartir datos de biodiversidad.</dd>
                    </div>
                    <div class="grid grid-cols-[8rem_1fr] gap-5 border-b border-white/20 py-5">
                        <dt class="font-semibold text-[#9BD7A5]">GBIF</dt>
                        <dd class="text-sm leading-6 text-white/70">Referencia taxonómica y marco para la publicación interoperable.</dd>
                    </div>
                    <div class="grid grid-cols-[8rem_1fr] gap-5 py-5">
                        <dt class="font-semibold text-[#9BD7A5]">Trazabilidad</dt>
                        <dd class="text-sm leading-6 text-white/70">Relación verificable entre ejemplar, evento de colecta y gestión curatorial.</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section id="investigacion" class="scroll-mt-28 bg-white" aria-labelledby="titulo-investigacion">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
                <div class="grid gap-12 lg:grid-cols-[0.95fr_1.05fr] lg:gap-20">
                    <div>
                        <h2 id="titulo-investigacion" class="font-display text-3xl font-bold tracking-[-0.02em] text-blue-navy sm:text-4xl">Investigación que parte de los ejemplares</h2>
                        <p class="mt-5 max-w-xl leading-8 text-text-secondary">
                            Una colección científica permite contrastar identificaciones, documentar distribuciones y producir evidencia reproducible sobre la diversidad biológica a lo largo del tiempo.
                        </p>
                        <a href="{{ route('portal.catalogo') }}" class="mt-7 inline-flex min-h-11 items-center gap-2 text-sm font-semibold !text-science-blue hover:underline">
                            Consultar datos disponibles
                            <svg viewBox="0 0 24 24" aria-hidden="true" class="size-4 fill-none stroke-current stroke-2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </a>
                    </div>

                    <div class="grid gap-x-10 gap-y-8 sm:grid-cols-2">
                        @foreach([
                            ['Taxonomía y sistemática', 'Identificación, comparación y documentación de la diversidad de invertebrados.'],
                            ['Distribución y biogeografía', 'Registros de ocurrencia asociados a localidades y periodos de colecta.'],
                            ['Conservación', 'Evidencia histórica para comprender cambios y apoyar decisiones informadas.'],
                            ['Formación científica', 'Material de referencia para docencia, tesis y desarrollo de capacidades.'],
                        ] as [$titulo, $descripcion])
                            <article class="border-l-2 border-bio-green pl-5">
                                <h3 class="font-display text-xl font-semibold text-blue-navy">{{ $titulo }}</h3>
                                <p class="mt-2 text-sm leading-6 text-text-secondary">{{ $descripcion }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section id="servicios" class="scroll-mt-28 border-y border-blue-navy/10 bg-[#F5F8FC]" aria-labelledby="titulo-servicios">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
                <div class="flex flex-col gap-5 border-b border-blue-navy/15 pb-9 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 id="titulo-servicios" class="font-display text-3xl font-bold tracking-[-0.02em] text-blue-navy sm:text-4xl">Servicios para la comunidad</h2>
                        <p class="mt-4 max-w-2xl leading-7 text-text-secondary">Acceso organizado para investigación, gestión de material y aprendizaje.</p>
                    </div>
                    <a href="#contacto" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold !text-science-blue hover:underline">Contactar al laboratorio <span aria-hidden="true">→</span></a>
                </div>

                <div class="grid divide-y divide-blue-navy/15 lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                    <article class="py-8 lg:pr-9">
                        <h3 class="font-display text-2xl font-semibold text-blue-navy">Consulta científica</h3>
                        <p class="mt-3 leading-7 text-text-secondary">Exploración del catálogo y atención de consultas sobre ejemplares, taxonomía y datos asociados.</p>
                        <a href="{{ route('portal.catalogo') }}" class="mt-5 inline-flex min-h-11 items-center gap-2 text-sm font-semibold !text-science-blue hover:underline">Explorar registros <span aria-hidden="true">→</span></a>
                    </article>
                    <article class="py-8 lg:px-9">
                        <h3 class="font-display text-2xl font-semibold text-blue-navy">Depósito de material</h3>
                        <p class="mt-3 leading-7 text-text-secondary">Proceso digital guiado para proponer el ingreso de material, documentar su procedencia y seguir la revisión.</p>
                        <a href="{{ route('depositos.portal') }}" class="mt-5 inline-flex min-h-11 items-center gap-2 text-sm font-semibold !text-science-blue hover:underline">Iniciar el proceso <span aria-hidden="true">→</span></a>
                    </article>
                    <article class="py-8 lg:pl-9">
                        <h3 class="font-display text-2xl font-semibold text-blue-navy">Educación y divulgación</h3>
                        <p class="mt-3 leading-7 text-text-secondary">Recursos de apoyo para acercar la diversidad de invertebrados, las colecciones y el trabajo curatorial a nuevos públicos.</p>
                        <a href="#divulgacion" class="mt-5 inline-flex min-h-11 items-center gap-2 text-sm font-semibold !text-science-blue hover:underline">Conocer este trabajo <span aria-hidden="true">→</span></a>
                    </article>
                </div>
            </div>
        </section>

        <section id="depositos" class="scroll-mt-28 bg-white" aria-labelledby="titulo-depositos">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
                <div class="grid gap-12 lg:grid-cols-[0.78fr_1.22fr] lg:items-start lg:gap-20">
                    <div>
                        <div class="h-1 w-14 bg-bio-green" aria-hidden="true"></div>
                        <h2 id="titulo-depositos" class="mt-6 font-display text-3xl font-bold tracking-[-0.02em] text-blue-navy sm:text-4xl">Integrar material a la colección</h2>
                        <p class="mt-5 leading-8 text-text-secondary">El módulo de depósitos acompaña la preparación de formularios, la carga de permisos, la revisión curatorial y la entrega física de los ejemplares.</p>
                        <a href="{{ route('depositos.portal') }}" class="mt-8 inline-flex min-h-12 items-center justify-center gap-3 rounded-md bg-bio-green px-5 py-3 text-sm font-semibold !text-white transition hover:bg-[#246829] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bio-green focus-visible:ring-offset-2">
                            Ir al portal de depósitos
                            <svg viewBox="0 0 24 24" aria-hidden="true" class="size-4 fill-none stroke-current stroke-2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </a>
                    </div>

                    <ol class="grid gap-7 sm:grid-cols-2">
                        @foreach([
                            ['01', 'Revisa', 'Conoce requisitos y documentos aplicables antes de iniciar.'],
                            ['02', 'Registra', 'Completa los datos y adjunta la documentación en línea.'],
                            ['03', 'Da seguimiento', 'Atiende observaciones y consulta el estado de la revisión.'],
                            ['04', 'Formaliza', 'Entrega el material para constatación y generación del acta.'],
                        ] as [$numero, $titulo, $descripcion])
                            <li class="border-t border-blue-navy/20 pt-5">
                                <span class="font-display text-3xl font-semibold text-bio-green">{{ $numero }}</span>
                                <h3 class="mt-3 font-display text-xl font-semibold text-blue-navy">{{ $titulo }}</h3>
                                <p class="mt-2 text-sm leading-6 text-text-secondary">{{ $descripcion }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </section>

        <section id="divulgacion" class="scroll-mt-28 bg-[#EAF2EC]" aria-labelledby="titulo-divulgacion">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 py-16 sm:px-8 lg:grid-cols-[1.15fr_0.85fr] lg:items-center lg:gap-20 lg:py-20">
                <div>
                    <h2 id="titulo-divulgacion" class="font-display text-3xl font-bold tracking-[-0.02em] text-blue-navy sm:text-4xl">Una colección también es una herramienta educativa</h2>
                    <p class="mt-5 max-w-2xl leading-8 text-text-secondary">La documentación de los ejemplares ayuda a explicar cómo se reconoce una especie, por qué importa su procedencia y cómo las colecciones sostienen la memoria ambiental del país.</p>
                </div>
                <div class="border-l-2 border-bio-green pl-7">
                    <p class="font-display text-xl font-semibold leading-8 text-blue-navy">Investigar, enseñar y divulgar son partes de una misma responsabilidad: conservar conocimiento verificable para el futuro.</p>
                </div>
            </div>
        </section>

        <section id="equipo" class="scroll-mt-28 bg-white" aria-labelledby="titulo-equipo">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
                <div class="grid gap-12 lg:grid-cols-[0.72fr_1.28fr] lg:gap-20">
                    <div>
                        <h2 id="titulo-equipo" class="font-display text-3xl font-bold tracking-[-0.02em] text-blue-navy sm:text-4xl">Equipo y responsabilidades</h2>
                        <p class="mt-5 leading-8 text-text-secondary">El trabajo del laboratorio articula conocimiento taxonómico, cuidado de colecciones, gestión documental y atención a usuarios.</p>
                    </div>
                    <dl class="divide-y divide-blue-navy/15 border-y border-blue-navy/15">
                        <div class="grid gap-2 py-5 sm:grid-cols-[11rem_1fr] sm:gap-8">
                            <dt class="font-semibold text-blue-navy">Curaduría</dt>
                            <dd class="leading-7 text-text-secondary">Evalúa la pertinencia científica, la identificación y el ingreso formal del material.</dd>
                        </div>
                        <div class="grid gap-2 py-5 sm:grid-cols-[11rem_1fr] sm:gap-8">
                            <dt class="font-semibold text-blue-navy">Colecciones</dt>
                            <dd class="leading-7 text-text-secondary">Custodia ejemplares, registra movimientos y mantiene condiciones de conservación.</dd>
                        </div>
                        <div class="grid gap-2 py-5 sm:grid-cols-[11rem_1fr] sm:gap-8">
                            <dt class="font-semibold text-blue-navy">Datos y atención</dt>
                            <dd class="leading-7 text-text-secondary">Apoya la calidad de la información y orienta solicitudes de consulta, depósito y uso científico.</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <section id="contacto" class="scroll-mt-28 border-t border-blue-navy/10 bg-[#F5F8FC]" aria-labelledby="titulo-contacto">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 sm:px-8 lg:grid-cols-[1fr_auto] lg:items-center lg:py-16">
                <div>
                    <h2 id="titulo-contacto" class="font-display text-3xl font-bold text-blue-navy">Laboratorio de Invertebrados</h2>
                    <p class="mt-4 flex max-w-2xl items-start gap-3 leading-7 text-text-secondary">
                        <svg viewBox="0 0 24 24" aria-hidden="true" class="mt-1 size-5 shrink-0 fill-none stroke-bio-green stroke-2"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" /></svg>
                        <span>Departamento de Biología · Facultad de Ciencias · Escuela Politécnica Nacional · Ladrón de Guevara E11-253 · Quito, Ecuador</span>
                    </p>
                </div>
                <a href="mailto:adrian.troya@epn.edu.ec" class="inline-flex min-h-12 items-center justify-center gap-3 rounded-md bg-science-blue px-5 py-3 text-sm font-semibold !text-white transition hover:bg-[#1266b8] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2">
                    Escribir al laboratorio
                    <svg viewBox="0 0 24 24" aria-hidden="true" class="size-4 fill-none stroke-current stroke-2"><path d="M4 6h16v12H4zM4 7l8 6 8-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </a>
            </div>
        </section>
    </div>
@endcomponent
