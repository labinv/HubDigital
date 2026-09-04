<div class="p-4 sm:p-6 space-y-5">
    <div>
        <flux:heading size="xl" level="1" class="font-display">Recepción física de lotes</flux:heading>
        <flux:text class="mt-1 text-sm text-text-secondary">Verifica el código QR, el inventario entregado, el embalaje, el estado y el rotulado.</flux:text>
    </div>

    <flux:callout variant="info" icon="information-circle">
        <flux:callout.heading>Cadena de custodia</flux:callout.heading>
        <flux:callout.text>Tu constatación registra usuario, fecha y resultado. Curaduría generará y firmará el acta final después.</flux:callout.text>
    </flux:callout>

    <div class="grid gap-3">
        @forelse($solicitudes as $solicitud)
            @php $recepcion = $recepciones->get($solicitud->id); @endphp
            <div class="rounded-xl border border-border bg-surface p-4 shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-xs text-text-secondary">{{ $solicitud->numero }}</span>
                        <flux:badge size="sm" color="sky">{{ $solicitud->tipo_tramite }}</flux:badge>
                        @if($recepcion)
                            <x-gestionprestamosrecepciones::recepcion-status-badge :estado="$recepcion->estado" />
                        @else
                            <flux:badge size="sm" color="amber">Pendiente de constatar</flux:badge>
                        @endif
                    </div>
                    <p class="mt-2 font-medium text-text-primary">{{ $nombres[$solicitud->investigador_id] ?? $solicitud->nombre_investigador_documento }}</p>
                    <p class="mt-1 text-xs text-text-secondary">Lote {{ $solicitud->codigo_qr }} Â· {{ $solicitud->grupo_animal ?? 'Grupo por confirmar' }}</p>
                </div>
                <flux:button class="mt-3 w-full sm:mt-0 sm:w-auto" variant="primary" icon="clipboard-document-check" wire:navigate
                    href="{{ route('prestamos.receptor.deposito.recepcion', $solicitud->id) }}">
                    {{ $recepcion ? 'Ver constatación' : 'Iniciar constatación' }}
                </flux:button>
            </div>
        @empty
            <div class="rounded-xl border border-border bg-surface p-10 text-center text-sm text-text-secondary">No hay lotes aprobados pendientes de entrega.</div>
        @endforelse
    </div>
</div>
