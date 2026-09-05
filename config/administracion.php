<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Alta inicial del administrador (solo desarrollo)
    |--------------------------------------------------------------------------
    |
    | Este acceso existe únicamente para que una instalación vacía pueda crear
    | su primer administrador desde un navegador real. Se cierra de forma
    | automática cuando ya existe una cuenta ADMIN, cuando vence el token o
    | cuando el host/entorno no está expresamente autorizado.
    |
    */
    'bootstrap' => [
        'enabled' => (bool) env('ADMIN_BOOTSTRAP_ENABLED', false),
        'token' => env('ADMIN_BOOTSTRAP_TOKEN'),
        'expires_at' => env('ADMIN_BOOTSTRAP_EXPIRES_AT'),
        'email' => env('ADMIN_BOOTSTRAP_EMAIL', 'adrian.troya@epn.edu.ec'),
        'allowed_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(',', (string) env(
                'ADMIN_BOOTSTRAP_ALLOWED_HOSTS',
                'localhost,127.0.0.1,dev.labinvepn.org',
            )),
        ))),
        'allowed_environments' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ADMIN_BOOTSTRAP_ALLOWED_ENVIRONMENTS', 'local,development,testing')),
        ))),
    ],
];
