<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes;

use Modules\GestionPrestamosRecepciones\Application\Ports\SolicitudFirmadaPort;

final readonly class FakeSolicitudFirmadaAdapter implements SolicitudFirmadaPort
{
    public function __construct(private bool $firmada = true) {}

    public function estaFirmada(string $solicitudId): bool
    {
        return $this->firmada;
    }
}
