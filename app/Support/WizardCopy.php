<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class WizardCopy
{
    public static function text(string $clave): string
    {
        $defecto = (string) config('wizard-copy.'.$clave, '');
        if ($defecto === '') {
            throw new \InvalidArgumentException('Clave de texto del wizard no definida.');
        }

        $textos = Cache::remember('wizard-copy-overrides', 300, static fn (): array =>
            DB::table('usuarios.textos_wizard')->pluck('contenido', 'clave')->all()
        );

        return (string) ($textos[$clave] ?? $defecto);
    }

    public static function all(): array
    {
        return Cache::remember('wizard-copy-overrides', 300, static fn (): array =>
            DB::table('usuarios.textos_wizard')->pluck('contenido', 'clave')->all()
        );
    }

    public static function forget(): void
    {
        Cache::forget('wizard-copy-overrides');
    }
}
