<?php

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
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

test('registration canonicalizes email and never stores a plaintext password', function () {
    $this->post(route('register.store'), [
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'email' => '  ANA.PEREZ@EXAMPLE.COM ',
        'password' => 'password',
        'password_confirmation' => 'password',
        'rol' => RolUsuario::PRESTAMISTA->value,
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email_normalizado', 'ana.perez@example.com')->sole();

    expect($user->email)->toBe('ana.perez@example.com');
    expect($user->password)->not->toBe('password');
    expect(Hash::check('password', $user->password))->toBeTrue();
});

test('registration rejects a duplicate email regardless of case or spaces', function () {
    User::factory()->create(['email' => 'duplicado@example.com']);

    $this->post(route('register.store'), [
        'first_name' => 'Otra',
        'last_name' => 'Persona',
        'email' => ' DUPLICADO@EXAMPLE.COM ',
        'password' => 'password',
        'password_confirmation' => 'password',
        'rol' => RolUsuario::DEPOSITANTE->value,
        'cargo' => 'Consultora',
        'institucion' => 'Entidad de prueba',
    ])->assertSessionHasErrors('email');

    expect(User::query()->where('email_normalizado', 'duplicado@example.com')->count())->toBe(1);
    $this->assertGuest();
});

test('public registration rejects every internal role', function (RolUsuario $rol) {
    $this->post(route('register.store'), [
        'first_name' => 'Cuenta',
        'last_name' => 'No autorizada',
        'email' => strtolower($rol->value).'@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'rol' => $rol->value,
    ])->assertSessionHasErrors('rol');

    $this->assertGuest();
})->with([
    RolUsuario::CURADOR,
    RolUsuario::RECEPTOR,
    RolUsuario::ADMIN,
]);
