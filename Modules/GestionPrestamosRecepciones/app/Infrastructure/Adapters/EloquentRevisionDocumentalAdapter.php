<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\RevisionDocumentalPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

final class EloquentRevisionDocumentalAdapter implements RevisionDocumentalPort
{
    public function registrarSolicitud(string $solicitudId, array $revision): void
    {
        $modelo = SolicitudDepositoEloquentModel::query()->whereKey($solicitudId)->lockForUpdate()->firstOrFail();
        $metadatos = $modelo->extraccion_metadatos ?? [];
        $revision['version_documental_persistida'] = $this->huellaDocumentosPersistidos($modelo);
        $revision['version'] = count($metadatos['revision_documental_historial'] ?? []) + 1;
        $historial = $metadatos['revision_documental_historial'] ?? [];
        $historial[] = $revision;
        $metadatos['revision_documental'] = $revision;
        $metadatos['revision_documental_historial'] = $historial;
        $modelo->forceFill(['extraccion_metadatos' => $metadatos])->save();
    }

    public function resolver(string $solicitudId, array $resolucion): void
    {
        $modelo = SolicitudDepositoEloquentModel::query()->whereKey($solicitudId)->lockForUpdate()->firstOrFail();
        $metadatos = $modelo->extraccion_metadatos ?? [];
        $actual = $metadatos['revision_documental'] ?? [];
        $resolucion['version_documental_persistida'] = $actual['version_documental_persistida']
            ?? $this->huellaDocumentosPersistidos($modelo);
        $metadatos['revision_documental'] = [...$actual, ...$resolucion];
        $historial = $metadatos['revision_documental_historial'] ?? [];
        $historial[] = $metadatos['revision_documental'];
        $metadatos['revision_documental_historial'] = $historial;
        $modelo->forceFill(['extraccion_metadatos' => $metadatos])->save();
    }

    private function huellaDocumentosPersistidos(SolicitudDepositoEloquentModel $modelo): string
    {
        $documentos = $modelo->documentos_cargados ?? [];
        ksort($documentos);

        return hash('sha256', json_encode($documentos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
