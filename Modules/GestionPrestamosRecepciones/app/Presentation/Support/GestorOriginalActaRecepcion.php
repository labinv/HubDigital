<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionOutput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/** Conserva versiones inmutables del PDF oficial antes de su firma electrónica. */
final class GestorOriginalActaRecepcion
{
    public function __construct(private readonly AlmacenamientoDepositos $almacenamiento) {}

    /** @return array{referencia:string,ruta:string,sha256:string,version:int} */
    public function materializar(
        string $solicitudId,
        string $actorId,
        ConsultarDetalleRecepcionOutput $recepcion,
        GeneradorPdfActaRecepcion $generador,
        bool $reemitir = false,
        ?int $versionEsperada = null,
    ): array {
        $actual = RecepcionLoteEloquentModel::query()->where('solicitud_deposito_id', $solicitudId)->firstOrFail();
        if (! $reemitir && $actual->acta_original_referencia !== null) {
            return $this->obtenerVerificado($solicitudId);
        }
        if ($reemitir && $actual->acta_firmada_ruta !== null) {
            throw new \DomainException('No se puede reemitir un acta que ya fue firmada.');
        }

        $referencia = (string) Str::uuid();
        $ruta = "actas/recepcion/original/{$solicitudId}/{$referencia}.pdf";
        $contenido = $generador->generar($recepcion);
        $sha256 = hash('sha256', $contenido);
        $this->almacenamiento->guardarContenido($ruta, $contenido, 'application/pdf');

        try {
            $resultado = DB::transaction(function () use ($solicitudId, $actorId, $reemitir, $referencia, $ruta, $sha256): array {
                $lote = RecepcionLoteEloquentModel::query()
                    ->where('solicitud_deposito_id', $solicitudId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $reemitir && $lote->acta_original_referencia !== null) {
                    return $this->obtenerVerificado($solicitudId);
                }

                if ($reemitir && $versionEsperada !== null && (int) $lote->acta_original_version !== $versionEsperada) {
                    throw new \DomainException('El original cambió antes de la reemisión. Actualice la pantalla y revise la versión vigente.');
                }

                if ($reemitir && $lote->acta_firmada_ruta !== null) {
                    throw new \DomainException('No se puede reemitir un acta que ya fue firmada.');
                }

                $historial = $lote->acta_original_historial ?? [];
                if ($lote->acta_original_referencia !== null) {
                    $historial[] = [
                        'referencia' => $lote->acta_original_referencia,
                        'ruta' => $lote->acta_original_ruta,
                        'sha256' => $lote->acta_original_sha256,
                        'version' => (int) $lote->acta_original_version,
                        'reemplazada_en' => now()->toIso8601String(),
                        'reemplazada_por' => $actorId,
                        'motivo' => 'reemitida por indisponibilidad del original',
                    ];
                }

                $version = max(0, (int) $lote->acta_original_version) + 1;
                $lote->forceFill([
                    'acta_original_referencia' => $referencia,
                    'acta_original_ruta' => $ruta,
                    'acta_original_sha256' => $sha256,
                    'acta_original_version' => $version,
                    'acta_original_materializada_en' => now(),
                    'acta_original_historial' => $historial,
                ])->save();

                return compact('referencia', 'ruta', 'sha256', 'version');
            });
            if ($resultado['referencia'] !== $referencia) {
                // Otra solicitud concurrente preparó el original vigente antes de
                // nuestro bloqueo. El objeto no referenciado se descarta.
                $this->almacenamiento->eliminar($ruta);
            }

            return $resultado;
        } catch (\Throwable $exception) {
            // El objeto sólo se elimina si la transacción no alcanzó a referenciarlo.
            // Un fallo posterior al commit se conserva para no borrar evidencia oficial.
            if (! $this->estaReferenciado($solicitudId, $ruta)) {
                $this->almacenamiento->eliminar($ruta);
            }
            throw $exception;
        }
    }

    /** @return array{referencia:string,ruta:string,sha256:string,version:int,contenido:string} */
    public function obtenerVerificado(string $solicitudId): array
    {
        $lote = RecepcionLoteEloquentModel::query()->where('solicitud_deposito_id', $solicitudId)->firstOrFail();
        if ($lote->acta_original_referencia === null || $lote->acta_original_ruta === null || $lote->acta_original_sha256 === null) {
            throw new \DomainException('El expediente no conserva un original oficial verificable.');
        }
        if (! $this->almacenamiento->existe($lote->acta_original_ruta)) {
            throw new \DomainException('El original oficial no está disponible. Reemita explícitamente una nueva versión.');
        }
        $contenido = $this->almacenamiento->obtener($lote->acta_original_ruta);
        if (! hash_equals($lote->acta_original_sha256, hash('sha256', $contenido))) {
            throw new \DomainException('La huella del original oficial no coincide con el objeto almacenado.');
        }

        return [
            'referencia' => $lote->acta_original_referencia,
            'ruta' => $lote->acta_original_ruta,
            'sha256' => $lote->acta_original_sha256,
            'version' => (int) $lote->acta_original_version,
            'contenido' => $contenido,
        ];
    }

    public function disponible(string $solicitudId): bool
    {
        try {
            $this->obtenerVerificado($solicitudId);
            return true;
        } catch (\DomainException) {
            return false;
        }
    }

    public function versionActual(string $solicitudId): int
    {
        return (int) (RecepcionLoteEloquentModel::query()
            ->where('solicitud_deposito_id', $solicitudId)
            ->value('acta_original_version') ?? 0);
    }

    private function estaReferenciado(string $solicitudId, string $ruta): bool
    {
        return RecepcionLoteEloquentModel::query()
            ->where('solicitud_deposito_id', $solicitudId)
            ->where('acta_original_ruta', $ruta)
            ->exists();
    }
}
