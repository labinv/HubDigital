<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Hash señuelo con el mismo driver y coste configurado que las claves reales. */
final class DummyPasswordHash
{
    public function value(): string
    {
        $driver = (string) config('hashing.driver', 'bcrypt');
        $options = (array) config("hashing.{$driver}", []);
        $cacheKey = 'auth:dummy-password-hash:v1:'.hash('sha256', serialize([$driver, $options]));

        return Cache::rememberForever(
            $cacheKey,
            static fn (): string => Hash::make(Str::random(64)),
        );
    }
}
