<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ResolverRevisionDocumentalPrevia;

final readonly class ResolverRevisionDocumentalPreviaInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
        public bool $favorable,
        public string $motivo = '',
        public bool $definitiva = false,
        /** @var list<string> */
        public array $hallazgosResueltos = [],
    ) {}
}
