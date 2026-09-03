<?php

use App\Enums\RolUsuario;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'rol' => RolUsuario::PRESTAMISTA->value,
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(auth()->user()->rol)->toBe(RolUsuario::PRESTAMISTA);
});

test('public registration cannot create a curator account', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'Curador',
        'last_name' => 'No Autorizado',
        'email' => 'curador-publico@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'rol' => RolUsuario::CURADOR->value,
    ]);

    $response->assertSessionHasErrors('rol');
    $this->assertGuest();
});
