<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Revoca accesos anteriores y conserva solo la solicitud web actual. */
final class InvalidateUserAccess
{
    public function keepingCurrentRequest(User $user): void
    {
        $user->tokens()->delete();

        // Se elimina también el ID actual de la tabla antes de migrar la sesión.
        // Así una copia de la cookie anterior deja de ser válida inmediatamente.
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }

        $user->forceFill(['remember_token' => Str::random(60)])->save();

        session()->regenerate(true);
        session()->forget('auth.password_confirmed_at');
    }
}
