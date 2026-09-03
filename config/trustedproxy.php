<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Proxies confiables
    |--------------------------------------------------------------------------
    |
    | Acepta una IP, una lista separada por comas o "*". El comodín solo debe
    | usarse cuando el origen no sea accesible directamente y todo el tráfico
    | llegue mediante un proxy controlado, como Cloudflare Tunnel.
    |
    */
    'proxies' => env('TRUSTED_PROXIES'),
];
