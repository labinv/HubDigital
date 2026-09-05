<div data-testid="admin-users-page" class="flex h-full w-full flex-1 flex-col gap-6 p-4 sm:p-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-bio-green">Gobierno de acceso</p>
            <h1 class="mt-1 font-display text-2xl font-bold text-blue-navy">Usuarios y perfiles</h1>
            <p class="mt-1 max-w-2xl text-sm text-text-secondary">
                Consulta las cuentas ciudadanas e internas y crea perfiles con un único rol autorizado.
            </p>
        </div>
        <flux:button
            wire:click="alternarFormulario"
            variant="primary"
            icon="user-plus"
            data-testid="admin-user-create-toggle"
        >
            {{ $mostrarFormulario ? 'Cerrar formulario' : 'Crear usuario' }}
        </flux:button>
    </header>

    @if (session('usuario-creado'))
        <div role="status" class="rounded-lg border border-bio-green/25 bg-bio-green/5 px-4 py-3 text-sm text-bio-green">
            {{ session('usuario-creado') }}
        </div>
    @endif

    @if (session('usuario-actualizado'))
        <div role="status" class="rounded-lg border border-science-blue/25 bg-science-blue/5 px-4 py-3 text-sm text-science-blue">
            {{ session('usuario-actualizado') }}
        </div>
    @endif

    @if ($mostrarFormulario)
        <section class="rounded-xl border border-border bg-surface p-5 shadow-sm">
            <div class="mb-5">
                <h2 class="font-display text-lg font-semibold text-blue-navy">Nueva cuenta</h2>
                <p class="text-sm text-text-secondary">
                    Las cuentas internas deben usar un correo institucional EPN. Cada persona debe verificar su correo antes de acceder.
                </p>
            </div>

            <form
                x-data="{ enviando: false }"
                x-on:submit.prevent="
                    if (enviando) return;
                    enviando = true;
                    const clave = $el.querySelector('input[name=password]');
                    const confirmacion = $el.querySelector('input[name=password_confirmation]');
                    try {
                        await $wire.crear(clave.value, confirmacion.value);
                    } finally {
                        clave.value = '';
                        confirmacion.value = '';
                        enviando = false;
                    }
                "
                data-testid="admin-user-create-form"
                class="grid gap-4 md:grid-cols-2"
            >
                <flux:input wire:model="first_name" name="first_name" label="Nombres" required autocomplete="off" />
                <flux:input wire:model="last_name" name="last_name" label="Apellidos" required autocomplete="off" />
                <flux:input wire:model="email" name="email" type="email" label="Correo electrónico" required autocomplete="off" />
                <flux:select wire:model.live="rol" name="rol" label="Rol" required>
                    @foreach ($roles as $opcionRol)
                        <flux:select.option value="{{ $opcionRol->value }}">{{ $opcionRol->etiqueta() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="cargo" name="cargo" label="Cargo o función" autocomplete="off" />
                <flux:input wire:model="institucion" name="institucion" label="Institución" autocomplete="off" />
                <flux:input name="password" type="password" label="Contraseña inicial" required minlength="12" maxlength="72" autocomplete="new-password" />
                <flux:input name="password_confirmation" type="password" label="Confirmar contraseña" required minlength="12" maxlength="72" autocomplete="new-password" />
                <p class="md:col-span-2 text-xs text-text-secondary">
                    Usa al menos 12 caracteres, mayúsculas, minúsculas, números y símbolos. La contraseña inicial no vence automáticamente; la persona puede cambiarla desde su cuenta.
                </p>

                @if ($errors->any())
                    <div role="alert" class="md:col-span-2 rounded-lg border border-error/25 bg-error/5 p-3 text-sm text-error">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="md:col-span-2 flex justify-end">
                    <flux:button type="submit" variant="primary" x-bind:disabled="enviando" data-testid="admin-user-create-submit">
                        Guardar usuario
                    </flux:button>
                </div>
            </form>
        </section>
    @endif

    @if ($usuarioEnEdicion)
        <section class="rounded-xl border border-science-blue/30 bg-surface p-5 shadow-sm">
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="font-display text-lg font-semibold text-blue-navy">Editar perfil institucional</h2>
                    <p class="text-sm text-text-secondary">El correo es la identidad de la cuenta y se conserva. Actualiza sus datos de perfil y su rol operativo.</p>
                </div>
                <flux:button wire:click="cancelarEdicion" variant="ghost" icon="x-mark">Cancelar</flux:button>
            </div>

            <form wire:submit="actualizar" class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="edicionFirstName" label="Nombres" required autocomplete="off" />
                <flux:input wire:model="edicionLastName" label="Apellidos" required autocomplete="off" />
                <flux:select wire:model="edicionRol" label="Rol operativo" required>
                    @foreach ($roles as $opcionRol)
                        <flux:select.option value="{{ $opcionRol->value }}">{{ $opcionRol->etiqueta() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="edicionCargo" label="Cargo o función" autocomplete="off" />
                <flux:input wire:model="edicionInstitucion" class="md:col-span-2" label="Institución" autocomplete="off" />

                @if ($errors->any())
                    <div role="alert" class="md:col-span-2 rounded-lg border border-error/25 bg-error/5 p-3 text-sm text-error">{{ $errors->first() }}</div>
                @endif

                <div class="md:col-span-2 flex justify-end">
                    <flux:button type="submit" variant="primary" icon="check">Guardar cambios</flux:button>
                </div>
            </form>
        </section>
    @endif

    <section class="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
        <div class="grid gap-3 border-b border-border p-4 md:grid-cols-[minmax(0,1fr)_16rem]">
            <flux:input
                wire:model.live.debounce.350ms="busqueda"
                icon="magnifying-glass"
                placeholder="Buscar por nombre, correo o institución"
                aria-label="Buscar usuarios"
                data-testid="admin-user-search"
            />
            <flux:select wire:model.live="filtroRol" aria-label="Filtrar por rol" data-testid="admin-user-role-filter">
                <flux:select.option value="">Todos los roles</flux:select.option>
                @foreach ($roles as $opcionRol)
                    <flux:select.option value="{{ $opcionRol->value }}">{{ $opcionRol->etiqueta() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border text-sm">
                <thead class="bg-bg-main/70 text-left text-xs font-semibold uppercase tracking-wide text-text-secondary">
                    <tr>
                        <th class="px-4 py-3">Persona</th>
                        <th class="px-4 py-3">Perfil</th>
                        <th class="px-4 py-3">Rol</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Alta</th>
                        <th class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($usuarios as $usuario)
                        <tr data-testid="admin-user-row" class="hover:bg-bg-main/50">
                            <td class="px-4 py-4">
                                <p class="font-medium text-text-primary">{{ $usuario->name }}</p>
                                <p class="text-xs text-text-secondary">{{ $usuario->email }}</p>
                            </td>
                            <td class="px-4 py-4 text-text-secondary">
                                <p>{{ $usuario->cargo ?: 'Sin cargo registrado' }}</p>
                                <p class="text-xs">{{ $usuario->institucion ?: 'Sin institución registrada' }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($usuario->rolesAsignados() as $rolAsignado)
                                        <span class="rounded-full bg-blue-navy/8 px-2.5 py-1 text-xs font-medium text-blue-navy">
                                            {{ $rolAsignado->etiqueta() }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $usuario->hasVerifiedEmail() ? 'text-bio-green' : 'text-amber-700' }}">
                                    <span class="size-1.5 rounded-full bg-current"></span>
                                    {{ $usuario->hasVerifiedEmail() ? 'Verificado' : 'Pendiente' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-xs text-text-secondary">
                                {{ $usuario->created_at?->format('d/m/Y') }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">
                                <div class="inline-flex items-center gap-1">
                                    @if (! $usuario->hasVerifiedEmail())
                                        <flux:button wire:click="reenviarVerificacion('{{ $usuario->id }}')" variant="ghost" size="sm" icon="envelope" title="Reenviar verificación">Verificar</flux:button>
                                    @endif
                                    <flux:button wire:click="editar('{{ $usuario->id }}')" variant="ghost" size="sm" icon="pencil-square">Editar</flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-text-secondary">
                                No se encontraron usuarios con estos criterios.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($usuarios->hasPages())
            <div class="border-t border-border p-4">{{ $usuarios->links() }}</div>
        @endif
    </section>
</div>
