<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;

/** Distingue una transición ya consumida por otra sesión de un error real. */
final class SolicitudDepositoYaProcesada extends DomainException
{
    public static function enviada(): self
    {
        return new self('Esta solicitud ya fue enviada y está pendiente de revisión.');
    }

    public static function aprobada(): self
    {
        return new self('Esta solicitud ya fue aprobada documentalmente.');
    }
}
