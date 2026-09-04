<div class="overflow-hidden bg-white text-text-primary">
    <section class="relative isolate border-b border-blue-navy/10 bg-white" aria-labelledby="titulo-depositos">
        <div class="mx-auto grid min-w-0 max-w-7xl lg:min-h-[34rem] lg:grid-cols-[0.9fr_1.1fr]">
            <div class="relative z-10 flex min-w-0 items-center px-4 py-12 sm:px-6 sm:py-16 lg:px-8 lg:py-20">
                <div class="min-w-0 max-w-2xl">
                    <h1 id="titulo-depositos" class="font-display text-4xl font-bold leading-[1.08] tracking-[-0.025em] text-blue-navy sm:text-5xl lg:text-[3.5rem]">
                        <span class="block lg:whitespace-nowrap">Depósito de colecciones</span>
                        <span class="block">biológicas</span>
                    </h1>
                    <div class="mt-5 h-1 w-14 rounded-full bg-bio-green" aria-hidden="true"></div>
                    <p class="mt-6 max-w-xl text-lg leading-8 text-text-secondary">
                        Registra el depósito temporal o la donación de especímenes. El sistema lee tus documentos, recupera los datos útiles y te acompaña hasta la firma y el envío al equipo curatorial.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a
                            href="{{ route('depositos.solicitud.crear') }}"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-blue-navy px-5 py-3 text-sm font-semibold !text-white shadow-sm transition hover:bg-[#244872] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2"
                        >
                            <flux:icon name="document-plus" class="size-5" />
                            Iniciar una solicitud
                            <flux:icon name="arrow-right" class="size-4" />
                        </a>

                        @auth
                            @if(auth()->user()->esDepositante())
                                <a
                                    href="{{ route('depositos.mis-solicitudes') }}"
                                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-blue-navy/25 bg-white px-5 py-3 text-sm font-semibold !text-blue-navy transition hover:border-blue-navy/60 hover:bg-blue-navy/[0.03] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2"
                                >
                                    <flux:icon name="clipboard-document-list" class="size-5" />
                                    Ver mis solicitudes
                                </a>
                            @endif
                        @else
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-blue-navy/25 bg-white px-5 py-3 text-sm font-semibold !text-blue-navy transition hover:border-blue-navy/60 hover:bg-blue-navy/[0.03] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2"
                            >
                                <flux:icon name="arrow-right-end-on-rectangle" class="size-5" />
                                Ya tengo una cuenta
                            </a>
                        @endauth
                    </div>

                    <p class="mt-5 flex max-w-lg items-start gap-2 text-sm leading-6 text-text-secondary">
                        <flux:icon name="lock-closed" class="mt-0.5 size-4 shrink-0 text-bio-green" />
                        Puedes consultar esta información sin iniciar sesión. Solo te pediremos autenticarte al ingresar al formulario para proteger tu expediente.
                    </p>
                </div>
            </div>

            <figure class="relative min-h-72 min-w-0 max-w-full overflow-hidden sm:min-h-96 lg:min-h-full">
                <img
                    src="{{ asset('images/portal-depositos-hero.webp') }}"
                    alt="Gaveta de colección entomológica con especímenes y etiquetas de archivo"
                    class="absolute inset-0 size-full object-cover object-[62%_50%]"
                    fetchpriority="high"
                />
                <div class="absolute inset-y-0 left-0 hidden w-32 bg-gradient-to-r from-white to-transparent lg:block" aria-hidden="true"></div>
            </figure>
        </div>
    </section>

    <section class="border-b border-blue-navy/10 bg-[#F5F8FC]" aria-labelledby="proceso-deposito">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-14">
            <div class="max-w-2xl">
                <h2 id="proceso-deposito" class="font-display text-3xl font-bold tracking-tight text-blue-navy">Tu solicitud, paso a paso</h2>
                <p class="mt-3 leading-7 text-text-secondary">La entrega física se coordina únicamente cuando la documentación ha sido revisada.</p>
            </div>

            <ol class="relative mt-9 grid gap-6 md:grid-cols-4 md:gap-0">
                @foreach([
                    ['Registra', 'Completa los formularios y adjunta la documentación requerida.'],
                    ['Validamos', 'Leemos los PDF, extraemos sus códigos y comprobamos el expediente.'],
                    ['Curaduría revisa', 'El equipo evalúa la pertinencia y las condiciones de custodia.'],
                    ['Entrega y acta', 'La EPN constata el lote y curaduría emite el acta firmada.'],
                ] as $indice => [$titulo, $descripcion])
                    <li class="relative flex gap-4 md:block md:pr-7">
                        @if($indice < 3)
                            <div class="absolute left-4 top-8 hidden h-px w-[calc(100%-2rem)] bg-bio-green/45 md:block" aria-hidden="true"></div>
                        @endif
                        <div class="relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full border border-bio-green bg-white font-mono text-sm font-semibold text-bio-green">
                            {{ $indice + 1 }}
                        </div>
                        <div class="md:mt-5">
                            <h3 class="font-display text-lg font-semibold text-blue-navy">{{ $titulo }}</h3>
                            <p class="mt-1.5 text-sm leading-6 text-text-secondary">{{ $descripcion }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="bg-white" aria-labelledby="preparar-expediente">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1fr_1px_1fr] lg:gap-12 lg:px-8 lg:py-16">
            <article>
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-science-blue/10 text-science-blue">
                        <flux:icon name="document-text" class="size-5" />
                    </div>
                    <h2 id="preparar-expediente" class="font-display text-2xl font-bold text-blue-navy">Prepara tu expediente</h2>
                </div>
                <p class="mt-4 leading-7 text-text-secondary">Los requisitos se adaptan al tipo de trámite, procedencia y situación regulatoria declarada.</p>
                <ul class="mt-6 space-y-3 text-sm leading-6 text-text-secondary">
                    <li class="flex gap-3"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /><span>Solicitud de depósito o donación generada y firmada dentro de HubDigital.</span></li>
                    <li class="flex gap-3"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /><span>Autorización de recolección y guía de movilización, cuando correspondan.</span></li>
                    <li class="flex gap-3"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /><span>Evidencia de procedencia lícita, cesión o justificación institucional.</span></li>
                    <li class="flex gap-3"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /><span>Datos de depósito material MEPN y detalle biológico completados en formularios guiados.</span></li>
                </ul>
            </article>

            <div class="hidden bg-blue-navy/10 lg:block" aria-hidden="true"></div>

            <article>
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                        <flux:icon name="information-circle" class="size-5" />
                    </div>
                    <h2 class="font-display text-2xl font-bold text-blue-navy">Antes de trasladar el material</h2>
                </div>
                <ul class="mt-6 space-y-4 text-sm leading-6 text-text-secondary">
                    <li class="grid grid-cols-[1.5rem_1fr] gap-2"><span class="font-mono font-semibold text-amber-700">01</span><span>Los depósitos temporales admiten hasta tres solicitudes por depositante en cada año calendario.</span></li>
                    <li class="grid grid-cols-[1.5rem_1fr] gap-2"><span class="font-mono font-semibold text-amber-700">02</span><span>La recepción física no implica aceptación automática ni transferencia de propiedad.</span></li>
                    <li class="grid grid-cols-[1.5rem_1fr] gap-2"><span class="font-mono font-semibold text-amber-700">03</span><span>No envíes ni traslades especímenes hasta recibir instrucciones del equipo curatorial.</span></li>
                    <li class="grid grid-cols-[1.5rem_1fr] gap-2"><span class="font-mono font-semibold text-amber-700">04</span><span>Tus documentos y datos sensibles solo serán visibles para ti y el personal autorizado.</span></li>
                </ul>
            </article>
        </div>
    </section>

    <section class="bg-blue-navy text-white">
        <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-6 px-4 py-9 sm:px-6 md:flex-row md:items-center lg:px-8">
            <div class="flex items-start gap-4">
                <div class="hidden size-11 shrink-0 items-center justify-center rounded-lg border border-white/20 bg-white/10 sm:flex">
                    <flux:icon name="document-plus" class="size-6" />
                </div>
                <div>
                    <h2 class="font-display text-2xl font-bold">¿Listo para iniciar tu depósito?</h2>
                    <p class="mt-1.5 text-sm leading-6 text-white/75">Puedes guardar el avance y completar el trámite por etapas.</p>
                </div>
            </div>
            <a
                href="{{ route('depositos.solicitud.crear') }}"
                class="inline-flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-semibold !text-blue-navy transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-blue-navy"
            >
                Iniciar una solicitud
                <flux:icon name="arrow-right" class="size-4" />
            </a>
        </div>
    </section>
</div>
