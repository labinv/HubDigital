<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Crea o actualiza el depositante de bootstrap sin guardar su clave en Git. */
final class DepositanteBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('BOOTSTRAP_DEPOSITANTE_PASSWORD', '');
        if ($password === '') {
            throw new \RuntimeException('BOOTSTRAP_DEPOSITANTE_PASSWORD es obligatoria para habilitar la cuenta depositante.');
        }

        $email = User::normalizarEmail((string) env('BOOTSTRAP_DEPOSITANTE_EMAIL', 'hernan.troya@yahoo.com'));

        $usuario = User::query()->firstOrNew(['email_normalizado' => $email]);

        if (! $usuario->exists) {
            $usuario->fill([
                'first_name' => 'Hernán',
                'last_name' => 'Troya',
                'email' => $email,
                'password' => $password,
                'rol' => RolUsuario::DEPOSITANTE,
                'cargo' => 'Coordinador Técnico',
                'institucion' => 'Kintiflow',
            ]);
        } else {
            $usuario->fill([
                'rol' => RolUsuario::DEPOSITANTE,
                'cargo' => $usuario->cargo ?: 'Coordinador Técnico',
                'institucion' => $usuario->institucion ?: 'Kintiflow',
            ]);
        }

        $usuario->forceFill(['email_verified_at' => $usuario->email_verified_at ?? now()])->save();

        $usuario->load('roles');
        $usuario->asignarRol(RolUsuario::DEPOSITANTE);

        $this->command?->info("Cuenta depositante de bootstrap habilitada: {$email}");
    }
}
