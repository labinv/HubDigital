<div class="bg-bg-main text-text-primary">
    <section class="border-b border-border bg-surface">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1.25fr_0.75fr] lg:px-8 lg:py-20">
            <div class="max-w-3xl">
                <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-bio-green/25 bg-bio-green/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-bio-green">
                    <flux:icon name="building-library" class="size-4" />
                    Laboratorio de Invertebrados EPN
                </p>
                <h1 class="font-display text-4xl font-bold leading-tight text-blue-navy sm:text-5xl">
                    Depósito de colecciones biológicas
                </h1>
                <p class="mt-5 max-w-2xl text-lg leading-relaxed text-text-secondary">
                    Registra una solicitud de depósito temporal o donación, adjunta la documentación de procedencia y entrega la matriz de especímenes para revisión curatorial.
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('depositos.solicitud.crear') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-bio-green px-5 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-bio-green/90">
                        <flux:icon name="document-plus" class="size-5" />
                        Iniciar una solicitud
                    </a>

                    @auth
                        <a href="{{ route('depositos.mis-solicitudes') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-5 py-3 text-sm font-semibold text-blue-navy transition-colors hover:border-science-blue/40 hover:text-science-blue">
                            Ver mis solicitudes
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-5 py-3 text-sm font-semibold text-blue-navy transition-colors hover:border-science-blue/40 hover:text-science-blue">
                            Ya tengo una cuenta
                        </a>
                    @endauth
                </div>

                <p class="mt-4 flex items-start gap-2 text-sm text-text-secondary">
                    <flux:icon name="shield-check" class="mt-0.5 size-4 shrink-0 text-bio-green" />
                    Para ingresar al formulario deberás autenticarte y verificar tu correo electrónico.
                </p>
            </div>

            <aside class="rounded-2xl border border-border bg-bg-main p-6 shadow-sm" aria-labelledby="antes-de-empezar">
                <h2 id="antes-de-empezar" class="font-display text-xl font-semibold text-blue-navy">Antes de empezar</h2>
                <ol class="mt-5 space-y-4 text-sm text-text-secondary">
                    <li class="flex gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-science-blue/10 font-semibold text-science-blue">1</span><span>Prepara la información del responsable, institución y procedencia del material.</span></li>
                    <li class="flex gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-science-blue/10 font-semibold text-science-blue">2</span><span>Digitaliza permisos, autorizaciones o justificaciones en archivos legibles.</span></li>
                    <li class="flex gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-science-blue/10 font-semibold text-science-blue">3</span><span>Completa la matriz de especímenes con términos Darwin Core.</span></li>
                </ol>
            </aside>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8" aria-labelledby="proceso-deposito">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.14em] text-science-blue">Proceso trazable</p>
            <h2 id="proceso-deposito" class="mt-2 font-display text-3xl font-bold text-blue-navy">Qué ocurre con tu solicitud</h2>
            <p class="mt-3 leading-relaxed text-text-secondary">La entrega física se coordina únicamente después de la revisión documental.</p>
        </div>

        <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['01', 'Registro', 'Completa el formulario, declara el origen lícito y carga los documentos requeridos.'],
                ['02', 'Validación', 'El sistema revisa integridad documental, campos Darwin Core y consistencia taxonómica.'],
                ['03', 'Revisión curatorial', 'Curaduría evalúa pertinencia, estado del material y condiciones de custodia.'],
                ['04', 'Recepción', 'Se agenda la entrega, se verifica el lote y se emite el acta correspondiente.'],
            ] as [$numero, $titulo, $descripcion])
                <article class="rounded-xl border border-border bg-surface p-5 shadow-sm">
                    <p class="font-mono text-sm font-semibold text-bio-green">{{ $numero }}</p>
                    <h3 class="mt-3 font-display text-lg font-semibold text-blue-navy">{{ $titulo }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-text-secondary">{{ $descripcion }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="border-y border-border bg-surface">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-14 sm:px-6 lg:grid-cols-2 lg:px-8">
            <article>
                <h2 class="font-display text-2xl font-bold text-blue-navy">Documentación</h2>
                <p class="mt-3 leading-relaxed text-text-secondary">La lista exacta se calcula según el tipo de trámite, el origen de recolección y la situación regulatoria declarada.</p>
                <ul class="mt-5 space-y-3 text-sm text-text-secondary">
                    <li class="flex gap-2"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /> Formato de solicitud de depósito o donación.</li>
                    <li class="flex gap-2"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /> Autorización de recolección y permiso de movilización, cuando correspondan.</li>
                    <li class="flex gap-2"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /> Evidencia de procedencia lícita, cesión o justificación institucional.</li>
                    <li class="flex gap-2"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /> Matriz de especímenes con información taxonómica y de recolección.</li>
                </ul>
            </article>

            <article class="rounded-xl border border-amber-200 bg-amber-50 p-6">
                <div class="flex items-start gap-3">
                    <flux:icon name="information-circle" class="size-6 shrink-0 text-amber-700" />
                    <div>
                        <h2 class="font-display text-xl font-semibold text-amber-950">Condiciones importantes</h2>
                        <ul class="mt-3 space-y-2 text-sm leading-relaxed text-amber-950/80">
                            <li>El depósito temporal admite hasta tres solicitudes por depositante en cada año calendario.</li>
                            <li>La recepción no implica aceptación automática ni transferencia de propiedad.</li>
                            <li>No envíes ni traslades material hasta recibir instrucciones de curaduría.</li>
                            <li>La información sensible y los documentos solo serán visibles para el solicitante y el personal autorizado.</li>
                        </ul>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="flex flex-col items-start justify-between gap-6 rounded-2xl bg-blue-navy p-7 text-white sm:flex-row sm:items-center lg:p-9">
            <div>
                <h2 class="font-display text-2xl font-bold">¿Tienes el material y los documentos listos?</h2>
                <p class="mt-2 text-sm leading-relaxed text-white/75">Puedes guardar el avance y completar la solicitud por etapas.</p>
            </div>
            <a href="{{ route('depositos.solicitud.crear') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-semibold text-blue-navy transition-colors hover:bg-white/90">
                Comenzar ahora
                <flux:icon name="arrow-right" class="size-4" />
            </a>
        </div>
    </section>
</div>
