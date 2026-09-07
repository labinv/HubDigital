<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarRevisionDocumental;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/** Envía una solicitud con documentos cargados a revisión documental curatorial. */
final class SolicitarRevisionDocumentalHandler
{
    public function __construct(
        private readonly SolicitudDepositoRepositoryInterface $repo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
    ) {}

    public function __invoke(SolicitarRevisionDocumentalInput $input): void
    {
        $solicitud = $this->repo->buscarPorId(SolicitudDepositoId::from($input->solicitudId));
        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        $solicitud->solicitarRevisionDocumental();

        $this->transactionManager->executeTransactional(function () use ($solicitud, $input): void {
            $this->repo->guardar($solicitud);
            $modelo = SolicitudDepositoEloquentModel::query()
                ->whereKey((string) $solicitud->id())
                ->lockForUpdate()
                ->firstOrFail();
            $metadatos = $modelo->extraccion_metadatos ?? [];
            $metadatos['revision_documental'] = $input->revisionDocumental;
            $modelo->forceFill(['extraccion_metadatos' => $metadatos])->save();
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });
    }
}
