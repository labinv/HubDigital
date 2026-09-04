<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Cuentas desechables para ejecutar el recorrido integral en un entorno de desarrollo. */
final class DepositosDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Las cuentas demo de depósitos no pueden crearse fuera de local/testing.');
        }

        $password = (string) env('DEMO_DEPOSITOS_PASSWORD', '');
        if ($password === '') {
            $password = Str::password(20, symbols: false);
        }

        $cuentas = [
            [
                'email' => 'test.depositante@labinvepn.test',
                'first_name' => 'Consultora',
                'last_name' => 'MEPN Prueba',
                'cargo' => 'Especialista ambiental',
                'institucion' => 'Consultora de prueba HubDigital',
                'rol' => RolUsuario::DEPOSITANTE,
            ],
            [
                'email' => 'test.recepcion@labinvepn.test',
                'first_name' => 'Recepción',
                'last_name' => 'EPN Prueba',
                'cargo' => 'Técnico de recepción',
                'institucion' => 'Escuela Politécnica Nacional',
                'rol' => RolUsuario::RECEPTOR,
            ],
            [
                'email' => 'test.curaduria@labinvepn.test',
                'first_name' => 'Curaduría',
                'last_name' => 'EPN Prueba',
                'cargo' => 'Curador de la colección',
                'institucion' => 'Escuela Politécnica Nacional',
                'rol' => RolUsuario::CURADOR,
            ],
        ];

        foreach ($cuentas as $datos) {
            $rol = $datos['rol'];
            unset($datos['rol']);

            $usuario = User::query()->updateOrCreate(
                ['email' => $datos['email']],
                [...$datos, 'password' => $password, 'rol' => $rol->value],
            );
            $usuario->forceFill(['email_verified_at' => now()])->save();
            $usuario->roles()->delete();
            $usuario->roles()->create(['rol' => $rol->value]);
        }

        $this->command?->newLine();
        $this->command?->warn('Cuentas demo creadas solo para desarrollo:');
        foreach ($cuentas as $cuenta) {
            $this->command?->line('  '.$cuenta['email']);
        }
        $this->command?->warn('Contraseña temporal compartida: '.$password);
        $this->command?->warn('Elimina estas cuentas al terminar la prueba.');
    }
}
