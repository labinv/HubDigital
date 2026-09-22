<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Support;

use Illuminate\Support\Str;
use Modules\CatalogoPublico\Application\Ports\AlmacenamientoImagenesPort;
use Modules\CatalogoPublico\Domain\ValueObjects\ArchivoImagen;

/**
 * Almacenamiento volátil para Behat. No toca disco: registra los nombres
 * "guardados" para poder verificar que (no) se almacenó una imagen.
 */
final class FakeAlmacenamientoImagenes implements AlmacenamientoImagenesPort
{
    /** @var array<string, string> ruta => contenido */
    private array $almacenadas = [];

    public function guardar(string $contenido, string $nombreDeseado): ArchivoImagen
    {
        $ruta = 'divulgacion/imagenes/'.Str::uuid().'-'.$nombreDeseado;
        $this->almacenadas[$ruta] = $contenido;

        return ArchivoImagen::crear(
            nombreOriginal: $nombreDeseado,
            ruta: $ruta,
            disco: 'public',
            sha256: hash('sha256', $contenido),
        );
    }

    public function eliminar(ArchivoImagen $archivo): void
    {
        unset($this->almacenadas[$archivo->ruta]);
    }

    public function url(ArchivoImagen $archivo): string
    {
        return '/storage/'.$archivo->ruta;
    }

    public function fueAlmacenada(string $nombreOriginal): bool
    {
        foreach (array_keys($this->almacenadas) as $ruta) {
            if (str_ends_with($ruta, '-'.$nombreOriginal)) {
                return true;
            }
        }

        return false;
    }
}
