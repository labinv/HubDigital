<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

final readonly class ArchivoImagen
{
    private function __construct(
        public string $nombreOriginal,
        public string $ruta,
        public string $disco,
        public ?string $sha256,
    ) {}

    public static function crear(string $nombreOriginal, string $ruta, string $disco, ?string $sha256 = null): self
    {
        if (trim($nombreOriginal) === '') {
            throw new \InvalidArgumentException('El nombre original del archivo no puede ser vacío');
        }

        if (trim($ruta) === '') {
            throw new \InvalidArgumentException('La ruta de almacenamiento del archivo no puede ser vacía');
        }

        $huella = strtolower(trim((string) $sha256));
        if ($huella !== '' && preg_match('/^[a-f0-9]{64}$/', $huella) !== 1) {
            throw new \InvalidArgumentException('La huella SHA-256 de la imagen no es valida');
        }

        return new self(trim($nombreOriginal), trim($ruta), trim($disco), $huella === '' ? null : $huella);
    }
}
