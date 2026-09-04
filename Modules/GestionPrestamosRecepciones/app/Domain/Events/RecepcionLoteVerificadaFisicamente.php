<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoColeccion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento emitido cuando recepción EPN constata el lote y define el régimen de
 * tenencia propuesto. El alta en colección espera el acta final firmada.
 */
final readonly class RecepcionLoteVerificadaFisicamente
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public EstadoColeccion $estadoColeccion,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
