<?php

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('an administrator can provision a verified internal account from the console', function () {
    $this->artisan('usuarios:crear-interno', [
        'email' => ' CURADOR.PRUEBA@EPN.EDU.EC ',
        'rol' => 'curador',
        '--nombre' => 'Curadora',
        '--apellido' => 'Prueba',
    ])->expectsQuestion('Contraseña temporal', 'Temporal-Segura-2026!')
        ->expectsQuestion('Confirma la contraseña temporal', 'Temporal-Segura-2026!')
        ->assertSuccessful();

    $user = User::query()->where('email_normalizado', 'curador.prueba@epn.edu.ec')->sole();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->tieneRol(RolUsuario::CURADOR))->toBeTrue()
        ->and($user->esUsuarioInterno())->toBeTrue()
        ->and(Hash::check('Temporal-Segura-2026!', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('Temporal-Segura-2026!');
});

test('internal provisioning rejects external email domains', function () {
    $this->artisan('usuarios:crear-interno', [
        'email' => 'curador@example.org',
        'rol' => RolUsuario::CURADOR->value,
        '--nombre' => 'Cuenta',
        '--apellido' => 'Externa',
    ])->expectsOutput('El correo no pertenece a un dominio institucional autorizado.')
        ->assertFailed();

    expect(User::query()->where('email_normalizado', 'curador@example.org')->exists())->toBeFalse();
});
