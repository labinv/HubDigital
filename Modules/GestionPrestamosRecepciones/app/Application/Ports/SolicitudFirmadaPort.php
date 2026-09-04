<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/** Consulta de integridad previa al envio del formulario oficial del depositante. */
interface SolicitudFirmadaPort
{
    public function estaFirmada(string $solicitudId): bool;
}
