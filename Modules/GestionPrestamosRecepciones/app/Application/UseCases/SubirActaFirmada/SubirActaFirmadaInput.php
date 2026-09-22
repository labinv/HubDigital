<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada;

/**
 * Datos de entrada para subir el acta firmada.
 */
final readonly class SubirActaFirmadaInput
{
    public function __construct(
        public string $solicitudId,
        public string $investigadorId,
        public string $pdfFirmadoRuta,
        public ?string $documentoIdentidadRuta,
        public ?string $pdfFirmadoSha256 = null,
        public ?string $documentoIdentidadSha256 = null,
    ) {}
}
