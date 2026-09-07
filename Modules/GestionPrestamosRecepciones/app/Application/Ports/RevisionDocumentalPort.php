<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/** Bitácora versionada de revisión humana sobre documentos regulatorios. */
interface RevisionDocumentalPort
{
    /** @param array<string, mixed> $revision */
    public function registrarSolicitud(string $solicitudId, array $revision): void;

    /** @param array<string, mixed> $resolucion */
    public function resolver(string $solicitudId, array $resolucion): bool;
}
