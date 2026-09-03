<?php

test('el portal de depósitos es público', function (): void {
    $this->get('/depositos')
        ->assertOk()
        ->assertSee('Depósito de colecciones biológicas')
        ->assertSee('Iniciar una solicitud');
});

test('el formulario de depósito requiere autenticación', function (): void {
    $this->get('/depositos/solicitud')
        ->assertRedirect(route('login'));
});

test('la ruta intentada del formulario se conserva al pedir autenticación', function (): void {
    $this->get('/depositos/solicitud');

    expect(session('url.intended'))->toBe(url('/depositos/solicitud'));
});
