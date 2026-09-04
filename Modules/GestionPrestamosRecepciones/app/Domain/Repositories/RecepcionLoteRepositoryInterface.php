<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\RecepcionLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoQRLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RecepcionLoteId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Puerto de persistencia del agregado {@see RecepcionLote}.
 */
interface RecepcionLoteRepositoryInterface
{
    public function nextIdentity(): RecepcionLoteId;

    public function guardar(RecepcionLote $lote): void;

    public function buscarPorId(RecepcionLoteId $id): ?RecepcionLote;

    public function buscarPorSolicitudId(SolicitudDepositoId $solicitudId): ?RecepcionLote;

    /**
     * Recupera el agregado con bloqueo de escritura durante una transaccion.
     * Evita que dos curadores cierren el mismo expediente simultaneamente.
     */
    public function buscarPorSolicitudIdParaActualizar(SolicitudDepositoId $solicitudId): ?RecepcionLote;

    public function buscarPorCodigoQR(CodigoQRLote $codigoQR): ?RecepcionLote;
}
