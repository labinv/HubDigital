<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion;

final readonly class GenerarActaRecepcionInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
    ) {}
}
