<div class="p-4 sm:p-6 space-y-6">
    <div>
        <flux:heading size="xl" level="1" class="font-display">Recepción de colecciones</flux:heading>
        <flux:text class="mt-1 text-text-secondary">Constata la entrega física antes de remitir el expediente a curaduría.</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <a href="{{ route('prestamos.receptor.depositos') }}" wire:navigate class="rounded-xl border border-border bg-surface p-5 shadow-sm transition hover:border-science-blue/40">
            <p class="text-sm text-text-secondary">Pendientes de recepción</p>
            <p class="mt-2 text-3xl font-semibold text-blue-navy">{{ $pendientesRecepcion }}</p>
        </a>
        <div class="rounded-xl border border-border bg-surface p-5 shadow-sm">
            <p class="text-sm text-text-secondary">Lotes constatados</p>
            <p class="mt-2 text-3xl font-semibold text-bio-green">{{ $lotesRecibidos }}</p>
        </div>
    </div>
</div>
