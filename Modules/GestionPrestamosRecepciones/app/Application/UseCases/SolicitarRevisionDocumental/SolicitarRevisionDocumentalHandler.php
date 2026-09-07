<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarRevisionDocumental;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\RevisionDocumentalPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/** Envía una solicitud con documentos cargados a revisión documental curatorial. */
final class SolicitarRevisionDocumentalHandler
{
    public function __construct(
        private readonly SolicitudDepositoRepositoryInterface $repo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
        private readonly RevisionDocumentalPort $revisionDocumental,
    ) {}

    public function __invoke(SolicitarRevisionDocumentalInput $input): void
    {
        $this->transactionManager->executeTransactional(function () use ($input): void {
            $solicitud = $this->repo->buscarPorIdParaActualizar(SolicitudDepositoId::from($input->solicitudId));
            if ($solicitud === null) {
                throw SolicitudNoEncontradaException::conId($input->solicitudId);
            }
            $solicitud->solicitarRevisionDocumental();
            $this->repo->guardar($solicitud);
            $this->revisionDocumental->registrarSolicitud((string) $solicitud->id(), $input->revisionDocumental);
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });
    }
}
