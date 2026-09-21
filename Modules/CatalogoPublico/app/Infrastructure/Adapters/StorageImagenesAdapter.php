<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Support\Str;
use Modules\CatalogoPublico\Application\Ports\AlmacenamientoImagenesPort;
use Modules\CatalogoPublico\Domain\ValueObjects\ArchivoImagen;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

final class StorageImagenesAdapter implements AlmacenamientoImagenesPort
{
    private const DISCO = 'r2';

    private const CARPETA = 'divulgacion/imagenes';

    public function __construct(
        private readonly AlmacenamientoDepositos $almacenamiento,
    ) {}

    public function guardar(string $contenido, string $nombreDeseado): ArchivoImagen
    {
        $nombreFinal = $this->resolverColision($this->sanitizar($nombreDeseado));
        $ruta = self::CARPETA.'/'.$nombreFinal;

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido) ?: 'application/octet-stream';
        if (! str_starts_with($mime, 'image/')) {
            throw new \InvalidArgumentException('El archivo de divulgacion debe ser una imagen valida.');
        }
        $this->almacenamiento->guardarContenido($ruta, $contenido, $mime);

        return ArchivoImagen::crear(
            nombreOriginal: $nombreFinal,
            ruta: $ruta,
            disco: self::DISCO,
        );
    }

    private function resolverColision(string $nombre): string
    {
        $ruta = self::CARPETA.'/'.$nombre;

        if (! $this->almacenamiento->existe($ruta)) {
            return $nombre;
        }

        $info = pathinfo($nombre);
        $base = $info['filename'];
        $ext = isset($info['extension']) ? '.'.$info['extension'] : '';
        $n = 2;

        do {
            $candidato = $base.'_'.$n.$ext;
            $n++;
        } while ($this->almacenamiento->existe(self::CARPETA.'/'.$candidato));

        return $candidato;
    }

    public function eliminar(ArchivoImagen $archivo): void
    {
        $this->asegurarRutaCatalogo($archivo->ruta);
        $this->almacenamiento->eliminar($archivo->ruta);
    }

    public function url(ArchivoImagen $archivo): string
    {
        return self::urlPublica($archivo->ruta);
    }

    public static function urlPublica(string $ruta): string
    {
        self::asegurarRutaCatalogo($ruta);
        $objeto = rtrim(strtr(base64_encode($ruta), '+/', '-_'), '=');

        return route('portal.imagen', ['objeto' => $objeto]);
    }

    public static function rutaDesdeObjetoPublico(string $objeto): string
    {
        $normalizado = strtr($objeto, '-_', '+/');
        $relleno = (4 - strlen($normalizado) % 4) % 4;
        $ruta = base64_decode($normalizado.str_repeat('=', $relleno), true);
        if ($ruta === false) {
            throw new \InvalidArgumentException('Identificador de imagen invalido.');
        }
        self::asegurarRutaCatalogo($ruta);

        return $ruta;
    }

    private function sanitizar(string $nombre): string
    {
        return Str::of($nombre)->basename()->toString();
    }

    private static function asegurarRutaCatalogo(string $ruta): void
    {
        if (! str_starts_with($ruta, self::CARPETA.'/') || str_contains($ruta, '\\') || str_contains($ruta, '..')) {
            throw new \InvalidArgumentException('La ruta de imagen de divulgacion no es valida.');
        }
    }
}
