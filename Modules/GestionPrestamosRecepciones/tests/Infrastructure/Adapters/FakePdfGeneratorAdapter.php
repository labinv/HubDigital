<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\PdfGeneratorPort;

final class FakePdfGeneratorAdapter implements PdfGeneratorPort
{
    /** @var list<array{datos: array<string, mixed>, rutaDestino: string}> */
    private array $llamadas = [];

    /** @var list<string> */
    private array $eliminadas = [];

    /**
     * @param  array<string, mixed>  $datos
     */
    public function generarActa(array $datos): string
    {
        return '%PDF-fake';
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function generarActaYAlmacenar(array $datos, string $rutaDestino): string
    {
        $this->llamadas[] = [
            'datos' => $datos,
            'rutaDestino' => $rutaDestino,
        ];

        return hash('sha256', $this->generarActa($datos));
    }

    public function almacenarImagenPng(string $base64, string $rutaDestino): void
    {
        // no-op en tests
    }

    public function leerImagenBase64(string $ruta, ?string $sha256Esperado = null): ?string
    {
        return 'data:image/png;base64,ZmFrZQ==';
    }

    public function eliminar(string $ruta): void
    {
        $this->eliminadas[] = $ruta;
    }

    public function fueInvocado(): bool
    {
        return count($this->llamadas) > 0;
    }

    /**
     * @return list<array{datos: array<string, mixed>, rutaDestino: string}>
     */
    public function llamadas(): array
    {
        return $this->llamadas;
    }

    /** @return list<string> */
    public function eliminadas(): array
    {
        return $this->eliminadas;
    }
}
