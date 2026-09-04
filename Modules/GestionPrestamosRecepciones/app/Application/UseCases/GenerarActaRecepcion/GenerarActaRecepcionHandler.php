<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\RecepcionLoteNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

final class GenerarActaRecepcionHandler
{
    public function __construct(
        private readonly RecepcionLoteRepositoryInterface $recepcionRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
    ) {}

    public function __invoke(GenerarActaRecepcionInput $input): GenerarActaRecepcionOutput
    {
        $lote = $this->recepcionRepo->buscarPorSolicitudId(SolicitudDepositoId::from($input->solicitudId));
        if ($lote === null) {
            throw RecepcionLoteNoEncontradaException::conSolicitud($input->solicitudId);
        }

        $lote->generarActaRecepcion($input->curadorId);

        $this->transactionManager->executeTransactional(function () use ($lote): void {
            $this->recepcionRepo->guardar($lote);
            foreach ($lote->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        return new GenerarActaRecepcionOutput(
            actaEmitida: $lote->actaEmitida(),
            ruta: $lote->actaRecepcion()?->ruta() ?? '',
        );
    }
}
