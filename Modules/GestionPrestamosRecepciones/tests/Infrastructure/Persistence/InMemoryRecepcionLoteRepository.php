<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\RecepcionLote;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoQRLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RecepcionLoteId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

final class InMemoryRecepcionLoteRepository implements RecepcionLoteRepositoryInterface
{
    /** @var array<string, RecepcionLote> */
    private array $store = [];

    /** @var array<string, array{referencia: string, sha256: string}> */
    private array $originalesVigentes = [];

    public function nextIdentity(): RecepcionLoteId
    {
        return RecepcionLoteId::generate();
    }

    public function guardar(RecepcionLote $lote): void
    {
        $this->store[(string) $lote->id()] = $lote;
    }

    public function buscarPorId(RecepcionLoteId $id): ?RecepcionLote
    {
        return $this->store[(string) $id] ?? null;
    }

    public function buscarPorSolicitudId(SolicitudDepositoId $solicitudId): ?RecepcionLote
    {
        foreach ($this->store as $lote) {
            if ($lote->solicitudId()->equals($solicitudId)) {
                return $lote;
            }
        }

        return null;
    }

    public function buscarPorSolicitudIdParaActualizar(SolicitudDepositoId $solicitudId): ?RecepcionLote
    {
        // El repositorio de pruebas es monohilo; conserva el contrato sin bloqueo real.
        return $this->buscarPorSolicitudId($solicitudId);
    }

    public function registrarOriginalVigente(SolicitudDepositoId $solicitudId, string $referencia, string $sha256): void
    {
        $this->originalesVigentes[(string) $solicitudId] = compact('referencia', 'sha256');
    }

    public function coincideOriginalVigenteParaActualizar(SolicitudDepositoId $solicitudId, string $referencia, string $sha256): bool
    {
        $original = $this->originalesVigentes[(string) $solicitudId] ?? null;

        return $original !== null
            && hash_equals($original['referencia'], $referencia)
            && hash_equals($original['sha256'], $sha256);
    }

    public function buscarPorCodigoQR(CodigoQRLote $codigoQR): ?RecepcionLote
    {
        foreach ($this->store as $lote) {
            if ($lote->codigoQR()->equals($codigoQR)) {
                return $lote;
            }
        }

        return null;
    }
}
