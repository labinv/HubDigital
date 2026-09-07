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

    public function resolver(string $solicitudId, array $resolucion): bool
    {
        $modelo = SolicitudDepositoEloquentModel::query()->whereKey($solicitudId)->lockForUpdate()->firstOrFail();
        $metadatos = $modelo->extraccion_metadatos ?? [];
        $actual = $metadatos['revision_documental'] ?? [];
        $huellaActual = $this->huellaDocumentosPersistidos($modelo);
        if (is_array($actual)
            && isset($actual['version_documental_persistida'])
            && ! hash_equals((string) $actual['version_documental_persistida'], $huellaActual)) {
            $historial = $metadatos['revision_documental_historial'] ?? [];
            $historial[] = [...$actual, 'estado' => 'invalidada', 'invalidada_en' => now()->toIso8601String(), 'motivo_invalidacion' => 'Los documentos fueron sustituidos antes de resolver la revisión.'];
            $metadatos['revision_documental_historial'] = $historial;
            $metadatos['revision_documental'] = [...$actual, 'estado' => 'invalidada'];
            $modelo->forceFill(['extraccion_metadatos' => $metadatos])->save();

            return false;
        }
        if (! is_array($actual) || ($actual['estado'] ?? null) !== 'pendiente' || empty($actual['version_documental_persistida'])) {
            throw new \DomainException('No existe una revisión documental pendiente y versionada para resolver.');
        }
        $resolucion['version_documental_persistida'] = $actual['version_documental_persistida']
            ?? $huellaActual;
        // La decisión favorable queda limitada a las advertencias que el curador
        // vio en esta versión; un cambio o un hallazgo nuevo nunca queda cubierto.
        $resolucion['advertencias_resueltas'] = array_values(array_filter(
            $actual['advertencias'] ?? [],
            static fn (mixed $advertencia): bool => is_string($advertencia) && trim($advertencia) !== '',
        ));
        $metadatos['revision_documental'] = [...$actual, ...$resolucion];
        $historial = $metadatos['revision_documental_historial'] ?? [];
        $historial[] = $metadatos['revision_documental'];
        $metadatos['revision_documental_historial'] = $historial;
        $modelo->forceFill(['extraccion_metadatos' => $metadatos])->save();
        return true;
    }

    private function huellaDocumentosPersistidos(SolicitudDepositoEloquentModel $modelo): string
    {
        $documentos = $modelo->documentos_cargados ?? [];
        ksort($documentos);

        return hash('sha256', json_encode($documentos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
