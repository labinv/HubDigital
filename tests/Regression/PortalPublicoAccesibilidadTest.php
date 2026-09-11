<?php

test('el menú móvil expone estado y devuelve el foco al cerrarse con escape', function (): void {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/portal.blade.php');

    expect($layout)
        ->not->toBeFalse()
        ->toContain('x-ref="menuButton"')
        ->toContain("x-text=\"abierto ? 'Cerrar menú principal' : 'Abrir menú principal'\"")
        ->toContain('$refs.menuButton.focus()');
});

test('el chat conserva controles táctiles y refluye cuando el zoom reduce el viewport', function (): void {
    $chat = file_get_contents(dirname(__DIR__, 2).'/Modules/CatalogoPublico/resources/views/livewire/chat-bot-widget.blade.php');

    expect($chat)
        ->not->toBeFalse()
        ->toContain('h-[min(32rem,calc(100dvh-1rem))]')
        ->toContain('sm:h-[min(32rem,calc(100dvh-7rem))]')
        ->toContain('flex min-h-0 flex-1')
        ->toContain('p-1 sm:p-3')
        ->toContain('aria-label="Pregunta al bichochat"')
        ->toContain('required')
        ->toContain('inline-flex size-11 shrink-0')
        ->toContain('min-h-11 min-w-11')
        ->toContain("'hidden sm:flex' => \$abierto");
});
