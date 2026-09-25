@props([
    'pasos' => [],
    'pasoActual' => 1,
    'pasosCompletados' => [],
])

<div class="grid grid-cols-7 items-stretch gap-0.5 p-1">
    @foreach($pasos as $index => $paso)
        @php
            $numero = $index + 1;
            $esCompletado = in_array($numero, $pasosCompletados);
            $esActual = $numero === $pasoActual;
            $esDeshabilitado = !$esCompletado && !$esActual;
        @endphp

        <div aria-label="Paso {{ $numero }}: {{ $paso['label'] }}" @if($esActual) aria-current="step" @endif class="flex min-w-0 items-center justify-center gap-1 rounded-md px-0.5 py-1.5 sm:justify-start sm:px-1
            {{ $esActual ? 'bg-blue-navy/5' : '' }}
            {{ $esCompletado ? 'cursor-default' : '' }}
        ">
            <div class="shrink-0 size-5 rounded-full flex items-center justify-center text-xs font-semibold
                {{ $esCompletado ? 'bg-bio-green text-white' : '' }}
                {{ $esActual && !$esCompletado ? 'bg-blue-navy text-white' : '' }}
                {{ $esDeshabilitado ? 'bg-bg-main text-text-secondary border border-border' : '' }}
            ">
                @if($esCompletado)
                    <flux:icon name="check" class="size-4" />
                @else
                    {{ $numero }}
                @endif
            </div>

            <div class="hidden min-w-0 sm:block">
                <p class="text-[10px] font-semibold leading-3
                    {{ $esActual ? 'text-blue-navy' : ($esCompletado ? 'text-text-primary' : 'text-text-secondary') }}
                ">
                    {{ $paso['label'] }}
                </p>

            </div>
        </div>

    @endforeach
</div>
