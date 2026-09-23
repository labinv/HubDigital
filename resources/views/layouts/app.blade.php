<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <x-app-breadcrumbs />
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
