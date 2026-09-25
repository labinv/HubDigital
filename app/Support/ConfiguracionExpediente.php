<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ConfiguracionExpediente
{
    public const PREFIJO_PREDETERMINADO = 'MEPN-INV-DEP';

    public static function prefijo(): string
    {
        return Cache::remember('depositos-prefijo-expediente', 300, static fn (): string =>
            (string) (DB::table('usuarios.configuracion_expediente')->where('id', 1)->value('prefijo') ?? self::PREFIJO_PREDETERMINADO)
        );
    }

    public static function olvidar(): void
    {
        Cache::forget('depositos-prefijo-expediente');
    }
}
