<?php

use App\Services\Security\DummyPasswordHash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

test('dummy password hash uses the configured hasher and is cached', function () {
    Cache::flush();

    $service = app(DummyPasswordHash::class);
    $first = $service->value();
    $second = $service->value();

    expect($first)->toBe($second)
        ->and(Hash::check('a-password-that-was-never-used', $first))->toBeFalse();
});
