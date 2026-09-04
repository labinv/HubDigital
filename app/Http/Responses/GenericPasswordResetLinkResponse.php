<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

/** Evita revelar si un correo está o no registrado. */
final class GenericPasswordResetLinkResponse implements
    FailedPasswordResetLinkRequestResponse,
    SuccessfulPasswordResetLinkRequestResponse
{
    private const MESSAGE = 'Si existe una cuenta con ese correo, recibirás un enlace para restablecer la contraseña.';

    public function toResponse($request): Response
    {
        if ($request->expectsJson()) {
            return new JsonResponse(['message' => self::MESSAGE]);
        }

        return redirect()->back()->with('status', self::MESSAGE);
    }
}
