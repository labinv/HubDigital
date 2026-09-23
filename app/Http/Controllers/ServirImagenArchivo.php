<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RolUsuario;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ServirImagenArchivo
{
    public function __invoke(string $id, AlmacenamientoDepositos $almacenamiento): StreamedResponse
    {
        abort_unless(auth()->user()?->tieneAlgunRol(RolUsuario::CURADOR, RolUsuario::ADMIN), 403);

        $imagen = DB::table('divulgacion.imagenes_taxonomicas')
            ->where('id', $id)
            ->where('disco', 'r2')
            ->first(['ruta', 'sha256']);
        abort_if($imagen === null, 404);

        $ruta = (string) $imagen->ruta;
        abort_unless(str_starts_with($ruta, 'divulgacion/imagenes/')
            && ! str_contains($ruta, '..')
            && ! str_contains($ruta, '\\'), 404);

        try {
            $mime = $almacenamiento->mimeType($ruta);
            abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true), 404);
            $sha = strtolower(trim((string) $imagen->sha256));
            $contenido = $almacenamiento->obtenerVerificado($ruta, $sha !== '' ? $sha : null);
        } catch (\Throwable) {
            abort(404);
        }

        return response()->stream(static function () use ($contenido): void {
            echo $contenido;
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }
}
