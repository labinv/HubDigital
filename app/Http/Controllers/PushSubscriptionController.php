<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PushSubscriptionController extends Controller
{
    public function configuration(): JsonResponse
    {
        return response()->json([
            'enabled' => $this->configured(),
            'publicKey' => $this->configured() ? config('webpush.vapid.public_key') : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->configured(), 503, 'Las notificaciones push todavía no están configuradas.');

        $validated = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'in:aes128gcm,aesgcm'],
        ]);

        $this->assertAllowedEndpoint((string) $validated['endpoint']);

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['contentEncoding'] ?? 'aes128gcm',
        );

        return response()->json(['saved' => true], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2048'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->json(['deleted' => true]);
    }

    private function configured(): bool
    {
        return filled(config('webpush.vapid.subject'))
            && filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    private function assertAllowedEndpoint(string $endpoint): void
    {
        $host = Str::lower((string) parse_url($endpoint, PHP_URL_HOST));

        foreach (config('webpush.allowed_endpoint_hosts', []) as $permitido) {
            $permitido = Str::lower((string) $permitido);
            if ($host === ltrim($permitido, '.') || ($permitido[0] ?? '') === '.' && Str::endsWith($host, $permitido)) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'endpoint' => 'El proveedor de notificaciones del navegador no está permitido.',
        ]);
    }
}
