<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'rol' => [
                'required',
                Rule::in([
                    RolUsuario::PRESTAMISTA->value,
                    RolUsuario::DEPOSITANTE->value,
                ]),
            ],
            'cargo' => ['nullable', 'required_if:rol,'.RolUsuario::DEPOSITANTE->value, 'string', 'max:255'],
            'institucion' => ['nullable', 'required_if:rol,'.RolUsuario::DEPOSITANTE->value, 'string', 'max:255'],
        ])->validate();

        $rol = RolUsuario::from($input['rol']);

        $user = User::create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'rol' => $rol,
            'cargo' => $rol === RolUsuario::DEPOSITANTE ? $input['cargo'] : null,
            'institucion' => $rol === RolUsuario::DEPOSITANTE ? $input['institucion'] : null,
        ]);

        // Registra la membresía del rol primario en el pivote de roles.
        $user->asignarRol($user->rol);

        return $user;
    }
}
