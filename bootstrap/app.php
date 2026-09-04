<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\NormalizeAuthenticationEmail;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Middleware\EnsureOwnsDepositRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            NormalizeAuthenticationEmail::class,
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
            'deposit.owner' => EnsureOwnsDepositRequest::class,
            'ability' => CheckAbilities::class,
            'abilities' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (DomainException $e, Request $request): ?JsonResponse {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return null;
        });

        $exceptions->render(function (InvalidArgumentException $e, Request $request): ?JsonResponse {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return null;
        });
    })->create();
