<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Normaliza el identificador antes de que Fortify o el broker lo consulten. */
final class NormalizeAuthenticationEmail
{
    /** @var list<string> */
    private const ROUTES = [
        'login.store',
        'register.store',
        'password.email',
        'password.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->filled('email') && in_array($request->route()?->getName(), self::ROUTES, true)) {
            $request->merge([
                'email' => User::normalizarEmail((string) $request->input('email')),
            ]);
        }

        return $next($request);
    }
}
