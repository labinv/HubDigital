<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion;

final readonly class GenerarActaRecepcionOutput
{
    public function __construct(
        public bool $actaEmitida,
        public string $ruta,
    ) {}
}
