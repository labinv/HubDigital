<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\MantenerNombreTaxonomicoOriginal;

final readonly class MantenerNombreTaxonomicoOriginalInput
{
    public function __construct(
        public string $solicitudId,
        public string $matrizId,
        public string $registroId,
        public string $motivoJustificacion,
        public ?string $comentarioJustificacion = null,
    ) {}
}
