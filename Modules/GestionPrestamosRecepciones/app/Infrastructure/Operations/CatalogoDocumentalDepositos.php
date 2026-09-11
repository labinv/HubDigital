<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Operations;

use Illuminate\Support\Facades\DB;

/** Extrae referencias documentales sin cargar el contenido de los objetos. */
final class CatalogoDocumentalDepositos
{
    /** @return \Generator<int, array<string, mixed>> */
    public function referencias(?string $expediente = null, ?string $lote = null, int $tamanoLote = 100): \Generator
    {
        $solicitudes = DB::table('recepciones.solicitudes_deposito')
            ->when($expediente, fn ($q) => $q->where('numero', $expediente))
            ->orderBy('id');

        foreach ($solicitudes->lazyById(max(1, $tamanoLote), 'id') as $solicitud) {
            $documentos = $this->json($solicitud->documentos_adjuntos ?? '[]');
            foreach ($documentos as $documento) {
                if (is_array($documento) && is_string($documento['ruta'] ?? null)) {
                    yield $this->referencia($solicitud, 'original', $documento['ruta'], $documento['sha256'] ?? null, null);
                }
            }
            foreach ($this->json($solicitud->documentos_cargados ?? '{}') as $ruta) {
                if (is_string($ruta) && ! $this->yaIncluida($documentos, $ruta)) {
                    yield $this->referencia($solicitud, 'original_cargado', $ruta, null, null);
                }
            }
            $acta = $this->json($solicitud->acta_transferencia_dominio ?? 'null');
            if (is_string($acta['ruta'] ?? null)) {
                yield $this->referencia($solicitud, 'acta_transferencia', $acta['ruta'], $acta['sha256'] ?? null, null);
            }
            if (is_string($solicitud->solicitud_firmada_ruta ?? null)) {
                yield $this->referencia(
                    $solicitud,
                    'solicitud_firmada',
                    $solicitud->solicitud_firmada_ruta,
                    $solicitud->solicitud_firmada_sha256,
                    (int) $solicitud->solicitud_documento_version,
                );
            }
        }

        $recepciones = DB::table('recepciones.recepcion_lotes as recepcion')
            ->join('recepciones.solicitudes_deposito as solicitud', 'solicitud.id', '=', 'recepcion.solicitud_deposito_id')
            ->when($expediente, fn ($q) => $q->where('solicitud.numero', $expediente))
            ->when($lote, fn ($q) => $q->where('recepcion.codigo_qr', $lote))
            ->orderBy('recepcion.id')
            ->select('recepcion.*', 'solicitud.numero');

        foreach ($recepciones->lazyById(max(1, $tamanoLote), 'recepcion.id', 'id') as $recepcion) {
            if (is_string($recepcion->acta_original_ruta ?? null)) {
                yield $this->referencia($recepcion, 'acta_original', $recepcion->acta_original_ruta, $recepcion->acta_original_sha256, (int) $recepcion->acta_original_version, $recepcion->codigo_qr);
            }
            if (is_string($recepcion->acta_firmada_ruta ?? null)) {
                $metadata = $this->json($recepcion->firma_metadata ?? '{}');
                yield $this->referencia($recepcion, 'acta_firmada', $recepcion->acta_firmada_ruta, $metadata['sha256'] ?? $metadata['pdf_sha256'] ?? null, (int) $recepcion->acta_original_version, $recepcion->codigo_qr);
            }
        }
    }

    /** @return array<string, mixed> */
    private function referencia(object $fila, string $tipo, string $ruta, mixed $sha, ?int $version, ?string $lote = null): array
    {
        return ['expediente' => $fila->numero, 'lote' => $lote, 'tipo' => $tipo, 'ruta' => $ruta, 'sha256_esperado' => is_string($sha) && $sha !== '' ? $sha : null, 'version_esperada' => $version ?: null];
    }

    /** @return array<mixed> */
    private function json(mixed $valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }
        $decodificado = json_decode((string) $valor, true);

        return is_array($decodificado) ? $decodificado : [];
    }

    /** @param array<mixed> $documentos */
    private function yaIncluida(array $documentos, string $ruta): bool
    {
        foreach ($documentos as $documento) {
            if (is_array($documento) && ($documento['ruta'] ?? null) === $ruta) {
                return true;
            }
        }

        return false;
    }
}
