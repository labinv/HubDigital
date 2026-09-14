@use('App\Enums\RolUsuario')

@props([
    'context' => 'header',
])

@php
    $usuarioMenu = auth()->user();
    $rolActivoMenu = $usuarioMenu->rolActivo();
    $enSidebar = $context === 'sidebar';
    $posicionMenu = $enSidebar ? 'bottom' : 'top';
    [$badgeRol, $iconoRol] = match ($rolActivoMenu) {
        RolUsuario::DEPOSITANTE => ['bg-bio-green/10 text-bio-green', 'archive-box'],
        RolUsuario::PRESTAMISTA => ['bg-science-blue/10 text-science-blue', 'document-text'],
        RolUsuario::CURADOR => ['bg-blue-navy/10 text-blue-navy', 'shield-check'],
        RolUsuario::RECEPTOR => ['bg-amber-100 text-amber-800', 'clipboard-document-check'],
        RolUsuario::ADMIN => ['bg-violet-100 text-violet-800', 'users'],
    };
@endphp

<flux:dropdown :position="$posicionMenu" align="start">
    <button
        type="button"
        data-test="sidebar-menu-button"
        @class([
            'flex min-h-14 w-full items-center gap-3 rounded-lg p-2 text-start transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2',
            'text-white hover:bg-white/10 focus-visible:ring-offset-blue-navy' => $enSidebar,
            'text-text-primary hover:bg-bg-main focus-visible:ring-science-blue focus-visible:ring-offset-surface' => ! $enSidebar,
        ])
    >
        <div class="relative shrink-0">
            <flux:avatar :name="$usuarioMenu->name" :initials="$usuarioMenu->initials()" />
            <span @class([
                'absolute -bottom-0.5 -end-0.5 size-2.5 rounded-full bg-success ring-2',
                'ring-blue-navy' => $enSidebar,
                'ring-surface' => ! $enSidebar,
            ])></span>
        </div>
        <div class="flex min-w-0 flex-1 flex-col">
            <span @class([
                'truncate text-sm font-semibold',
                'text-white' => $enSidebar,
                'text-text-primary' => ! $enSidebar,
            ])>{{ $usuarioMenu->name }}</span>
            @if($enSidebar)
                <span class="truncate text-xs text-white/55">{{ $usuarioMenu->email }}</span>
                <span class="mt-1 inline-flex items-center gap-1 self-start text-[0.6875rem] font-medium text-white/75">
                    <flux:icon :name="$iconoRol" class="size-3" />
                    {{ $rolActivoMenu->etiqueta() }}
                </span>
            @else
                <span class="mt-1 inline-flex items-center gap-1 self-start rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeRol }}">
                    <flux:icon :name="$iconoRol" class="size-3" />
                    {{ $rolActivoMenu->etiqueta() }}
                </span>
            @endif
        </div>
        <flux:icon name="chevron-up-down" @class([
            'ms-auto size-4 shrink-0',
            'text-white/65' => $enSidebar,
            'text-text-secondary' => ! $enSidebar,
        ]) />
    </button>

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar
                :name="$usuarioMenu->name"
                :initials="$usuarioMenu->initials()"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ $usuarioMenu->name }}</flux:heading>
                <flux:text class="truncate">{{ $usuarioMenu->email }}</flux:text>
            </div>
        </div>

        <livewire:selector-rol-activo />

        @unless($enSidebar)
            <flux:menu.separator />
            <flux:menu.radio.group>
                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                    {{ __('Configuración') }}
                </flux:menu.item>
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item
                        as="button"
                        type="submit"
                        icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer"
                        data-test="logout-button"
                    >
                        {{ __('Cerrar sesión') }}
                    </flux:menu.item>
                </form>
            </flux:menu.radio.group>
        @endunless
    </flux:menu>
</flux:dropdown>
