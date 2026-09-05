<div class="hub-workspace space-y-6 p-4 sm:p-6">
    <header class="hub-page-header">
        <div>
            <p class="hub-page-kicker">Ventanilla institucional · EPN</p>
            <h1 class="mt-1 hub-page-title">Recepción de colecciones</h1>
            <p class="mt-2 text-sm text-text-secondary">Constata la entrega física antes de remitir el expediente a curaduría.</p>
        </div>
        <flux:button href="{{ route('prestamos.receptor.depositos') }}" wire:navigate variant="primary" icon="clipboard-document-check">
            Abrir lotes por recibir
        </flux:button>
    </header>

    <div class="grid gap-4 sm:grid-cols-2">
        <a href="{{ route('prestamos.receptor.depositos') }}" wire:navigate class="hub-panel p-5 transition hover:border-science-blue/40">
            <p class="text-sm text-text-secondary">Pendientes de recepción</p>
            <p class="mt-2 text-3xl font-semibold text-blue-navy">{{ $pendientesRecepcion }}</p>
        </a>
        <div class="hub-panel p-5">
            <p class="text-sm text-text-secondary">Lotes constatados</p>
            <p class="mt-2 text-3xl font-semibold text-bio-green">{{ $lotesRecibidos }}</p>
        </div>
    </div>
</div>
