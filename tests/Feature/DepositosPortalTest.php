<?php

test('el portal de depósitos es público', function (): void {
    $this->get('/depositos')
        ->assertOk()
        ->assertSee('Depósito de colecciones')
        ->assertSee('biológicas')
        ->assertSee('Iniciar una solicitud');
});

test('el portal respeta https cuando la solicitud llega por un proxy confiable', function (): void {
    config(['trustedproxy.proxies' => '*']);

    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.2'])
        ->withHeaders([
            'X-Forwarded-Host' => 'dev.labinvepn.org',
            'X-Forwarded-Proto' => 'https',
        ])
        ->get('/depositos')
        ->assertOk()
        ->assertSee('href="https://dev.labinvepn.org/depositos/solicitud"', false);
});

test('el formulario de depósito requiere autenticación', function (): void {
    $this->get('/depositos/solicitud')
        ->assertRedirect(route('login'));
});

test('la ruta intentada del formulario se conserva al pedir autenticación', function (): void {
    $this->get('/depositos/solicitud');

    expect(session('url.intended'))->toBe(url('/depositos/solicitud'));
});
