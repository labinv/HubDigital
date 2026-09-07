<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarRevisionDocumental;

final readonly class SolicitarRevisionDocumentalInput
{
    public function __construct(
        public string $solicitudId,
    ) {}
}
