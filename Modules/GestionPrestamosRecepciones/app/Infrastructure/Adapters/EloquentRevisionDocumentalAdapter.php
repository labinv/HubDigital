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
        $revision['hallazgos'] = $this->hallazgosEstructurados($revision, $revision['version_documental_persistida']);
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
        if (! is_array($actual) || ($actual['estado'] ?? null) !== 'pendiente' || ! is_string($actual['version_documental_persistida'] ?? null)) {
            throw new \DomainException('No existe una revisión documental pendiente, identificable y versionada para resolver.');
        }
        $huellaActual = $this->huellaDocumentosPersistidos($modelo);
        if (! hash_equals($actual['version_documental_persistida'], $huellaActual)) {
            $invalida = [...$actual, 'estado' => 'invalidada', 'invalidada_en' => now()->toIso8601String(), 'motivo_invalidacion' => 'Los documentos fueron sustituidos antes de resolver la revisión.'];
            $metadatos['revision_documental'] = $invalida;
            $metadatos['revision_documental_historial'] = [...($metadatos['revision_documental_historial'] ?? []), $invalida];
            $modelo->forceFill(['extraccion_metadatos' => $metadatos])->save();
            return false;
        }
        $permitidos = array_column(is_array($actual['hallazgos'] ?? null) ? $actual['hallazgos'] : [], 'id');
        $seleccionados = array_values(array_unique(array_filter($resolucion['hallazgos_resueltos'] ?? [], 'is_string')));
        if ($seleccionados === [] || array_diff($seleccionados, $permitidos) !== []) {
            throw new \DomainException('La resolución debe identificar únicamente hallazgos de la revisión documental vigente.');
        }
        $resolucion['version_documental_persistida'] = $actual['version_documental_persistida'];
        $resolucion['hallazgos_resueltos'] = $seleccionados;
        $metadatos['revision_documental'] = [...$actual, ...$resolucion];
        $metadatos['revision_documental_historial'] = [...($metadatos['revision_documental_historial'] ?? []), $metadatos['revision_documental']];
        $modelo->forceFill(['extraccion_metadatos' => $metadatos])->save();
        return true;
    }

    private function huellaDocumentosPersistidos(SolicitudDepositoEloquentModel $modelo): string
    {
        $documentos = $modelo->documentos_cargados ?? [];
        ksort($documentos);
        return hash('sha256', json_encode($documentos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @return list<array{id:string,tipo:string,descripcion:string,evidencia:string}> */
    private function hallazgosEstructurados(array $revision, string $version): array
    {
        $hallazgos = [];
        foreach (['errores', 'advertencias'] as $tipo) {
            foreach (is_array($revision[$tipo] ?? null) ? $revision[$tipo] : [] as $descripcion) {
                if (! is_string($descripcion) || trim($descripcion) === '') continue;
                $hallazgos[] = ['id' => hash('sha256', $version.'|'.$tipo.'|'.$descripcion), 'tipo' => $tipo, 'descripcion' => $descripcion, 'evidencia' => $version];
            }
        }
        return $hallazgos;
    }
}
