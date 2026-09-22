<?php

namespace App\Services\Security;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class TurnstileVerifier
{
    /**
     * Valida el token en el servidor antes de comprobar las credenciales.
     */
    public function validateLogin(Request $request): void
    {
        if (! config('services.turnstile.enabled')) {
            return;
        }

        $secret = (string) config('services.turnstile.secret');
        $token = (string) $request->input('cf-turnstile-response');

        if ($secret === '' || $token === '') {
            $this->reject();
        }

        try {
            $result = Http::asForm()
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(8)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException) {
            $this->reject('No fue posible validar que eres una persona. Intenta nuevamente.');
        }

        $expectedHostname = (string) config('services.turnstile.expected_hostname');
        $expectedAction = (string) config('services.turnstile.expected_action');

        if (($result['success'] ?? false) !== true
            || ($expectedHostname !== '' && ($result['hostname'] ?? null) !== $expectedHostname)
            || ($expectedAction !== '' && ($result['action'] ?? null) !== $expectedAction)) {
            $this->reject();
        }
    }

    private function reject(string $message = 'Completa la verificación de seguridad antes de iniciar sesión.'): never
    {
        throw ValidationException::withMessages([
            'cf-turnstile-response' => $message,
        ]);
    }
}
