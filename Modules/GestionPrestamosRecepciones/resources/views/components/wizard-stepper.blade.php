@props([
    'pasos' => [],
    'pasoActual' => 1,
    'pasosCompletados' => [],
])

<div class="grid auto-cols-[minmax(7.75rem,1fr)] grid-flow-col items-stretch gap-0.5 overflow-x-auto p-1 sm:auto-cols-auto sm:grid-flow-row sm:grid-cols-6 sm:overflow-visible">
    @foreach($pasos as $index => $paso)
        @php
            $numero = $index + 1;
            $esCompletado = in_array($numero, $pasosCompletados);
            $esActual = $numero === $pasoActual;
            $esDeshabilitado = !$esCompletado && !$esActual;
        @endphp

        <div class="flex min-w-[7.75rem] items-center gap-2 rounded-md px-2.5 py-2 sm:min-w-0
            {{ $esActual ? 'bg-blue-navy/5' : '' }}
            {{ $esCompletado ? 'cursor-default' : '' }}
        ">
            <div class="shrink-0 size-6 rounded-full flex items-center justify-center text-xs font-semibold
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

            <div class="min-w-0">
                <p class="text-xs font-semibold leading-4
                    {{ $esActual ? 'text-blue-navy' : ($esCompletado ? 'text-text-primary' : 'text-text-secondary') }}
                ">
                    {{ $paso['label'] }}
                </p>
                <p class="hidden text-[10px] leading-4 text-text-secondary lg:block">{{ $paso['sub'] }}</p>
            </div>
        </div>

    @endforeach
</div>
