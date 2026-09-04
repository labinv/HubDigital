<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistroUsuarioRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => User::normalizarEmail((string) $this->input('email'))]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email_normalizado')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
