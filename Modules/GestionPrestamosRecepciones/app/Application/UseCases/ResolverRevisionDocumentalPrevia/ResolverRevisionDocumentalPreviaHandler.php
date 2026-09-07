<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ResolverRevisionDocumentalPrevia;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\RevisionDocumentalPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

final class ResolverRevisionDocumentalPreviaHandler
{
    public function __construct(private readonly SolicitudDepositoRepositoryInterface $repo, private readonly TransactionManagerPort $transactionManager, private readonly EventPublisherPort $eventPublisher, private readonly RevisionDocumentalPort $revisionDocumental) {}

    public function __invoke(ResolverRevisionDocumentalPreviaInput $input): void
    {
        $motivo = trim($input->motivo) !== ''
            ? trim($input->motivo)
            : 'Documentación revisada. Continúe con la preparación, firma y envío de la solicitud.';
        $this->transactionManager->executeTransactional(function () use ($input, $motivo): void {
            $solicitud = $this->repo->buscarPorIdParaActualizar(SolicitudDepositoId::from($input->solicitudId));
            if ($solicitud === null) throw SolicitudNoEncontradaException::conId($input->solicitudId);
            $solicitud->resolverRevisionDocumentalPrevia($input->curadorId, $input->favorable, $motivo, $input->definitiva);
            $this->repo->guardar($solicitud);
            $this->revisionDocumental->resolver($input->solicitudId, [
                'estado' => $input->favorable ? 'favorable' : ($input->definitiva ? 'rechazada' : 'requiere_correccion'),
                'resuelta_por' => $input->curadorId,
                'resuelta_en' => now()->toIso8601String(),
                'decision' => $motivo,
            ]);
            foreach ($solicitud->pullEvents() as $event) $this->eventPublisher->publish($event);
        });
    }
}
