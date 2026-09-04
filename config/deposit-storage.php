<?php

declare(strict_types=1);

$cuentaR2 = trim((string) env('R2_ACCOUNT_ID', ''));
$endpointR2 = trim((string) env('R2_ENDPOINT', ''));

return [
    /*
    |--------------------------------------------------------------------------
    | Almacenamiento privado de expedientes de deposito
    |--------------------------------------------------------------------------
    |
    | "auto" usa R2 cuando todas las credenciales estan presentes. Solamente
    | en local/testing puede degradar a disco local cuando no hay credenciales.
    | Una configuracion parcial nunca se ignora: se rechaza para evitar creer
    | que existe una copia remota cuando en realidad solo se escribio localmente.
    |
    */
    'driver' => env('DEPOSIT_STORAGE_DRIVER', 'auto'),
    'require_remote' => filter_var(
        env('DEPOSIT_STORAGE_REQUIRE_REMOTE', env('APP_ENV', 'production') === 'production'),
        FILTER_VALIDATE_BOOL,
    ),
    'local_disk' => env('DEPOSIT_STORAGE_LOCAL_DISK', 'local'),
    'legacy_public_disk' => env('DEPOSIT_STORAGE_LEGACY_PUBLIC_DISK', 'public'),
    'verify_after_write' => filter_var(env('DEPOSIT_STORAGE_VERIFY_AFTER_WRITE', true), FILTER_VALIDATE_BOOL),
    'max_object_bytes' => (int) env('DEPOSIT_STORAGE_MAX_OBJECT_BYTES', 25 * 1024 * 1024),
    'temporary_directory' => storage_path('app/private/tmp/deposit-storage'),

    'r2' => [
        'account_id' => $cuentaR2,
        'bucket' => trim((string) env('R2_BUCKET', '')),
        'access_key_id' => trim((string) env('R2_ACCESS_KEY_ID', '')),
        'secret_access_key' => trim((string) env('R2_SECRET_ACCESS_KEY', '')),
        'endpoint' => $endpointR2 !== ''
            ? rtrim($endpointR2, '/')
            : ($cuentaR2 !== '' ? "https://{$cuentaR2}.r2.cloudflarestorage.com" : ''),
        'region' => 'auto',
        'timeout_seconds' => (int) env('R2_TIMEOUT_SECONDS', 45),
        'connect_timeout_seconds' => (int) env('R2_CONNECT_TIMEOUT_SECONDS', 10),
        'max_attempts' => (int) env('R2_MAX_ATTEMPTS', 3),
    ],
];
