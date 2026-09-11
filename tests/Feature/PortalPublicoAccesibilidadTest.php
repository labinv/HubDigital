<?php

test('el menú móvil expone estado y devuelve el foco al cerrarse con escape', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('x-ref="menuButton"', false)
        ->assertSee("x-text=\"abierto ? 'Cerrar menú principal' : 'Abrir menú principal'\"", false)
        ->assertSee('$refs.menuButton.focus()', false);
});

test('el chat conserva controles táctiles y refluye cuando el zoom reduce el viewport', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('h-[min(32rem,calc(100dvh-2rem))]', false)
        ->assertSee('sm:h-[min(32rem,calc(100dvh-7rem))]', false)
        ->assertSee('inline-flex size-11 shrink-0', false)
        ->assertSee('min-h-11 min-w-11', false)
        ->assertSee("'hidden sm:flex' => \$abierto", false);
});
