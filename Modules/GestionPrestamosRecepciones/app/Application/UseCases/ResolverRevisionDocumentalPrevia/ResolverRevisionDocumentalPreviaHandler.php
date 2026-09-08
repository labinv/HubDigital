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
    public function __construct(
        private readonly SolicitudDepositoRepositoryInterface $repo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
        private readonly RevisionDocumentalPort $revisionDocumental,
    ) {}

    public function __invoke(ResolverRevisionDocumentalPreviaInput $input): void
    {
        $motivo = trim($input->motivo);
        if ($motivo === '') throw new \DomainException('La revisión documental requiere una justificación explícita para el depositante.');
        if ($input->favorable && $input->hallazgosResueltos === []) throw new \DomainException('Seleccione al menos un hallazgo documental que haya sido resuelto.');

        $resuelta = false;
        $eventos = [];
        $this->transactionManager->executeTransactional(function () use ($input, $motivo, &$resuelta, &$eventos): void {
            $solicitud = $this->repo->buscarPorIdParaActualizar(SolicitudDepositoId::from($input->solicitudId));
            if ($solicitud === null) throw SolicitudNoEncontradaException::conId($input->solicitudId);
            $resuelta = $this->revisionDocumental->resolver($input->solicitudId, [
                'estado' => $input->favorable ? 'favorable' : ($input->definitiva ? 'rechazada' : 'requiere_correccion'),
                'resuelta_por' => $input->curadorId,
                'resuelta_en' => now()->toIso8601String(),
                'decision' => $motivo,
                'hallazgos_resueltos' => $input->hallazgosResueltos,
            ]);
            if (! $resuelta) return;
            $solicitud->resolverRevisionDocumentalPrevia($input->curadorId, $input->favorable, $motivo, $input->definitiva);
            $this->repo->guardar($solicitud);
            $eventos = $solicitud->pullEvents();
        });
        if (! $resuelta) throw new \DomainException('Los documentos cambiaron durante la revisión. Solicite una nueva revisión documental.');
        foreach ($eventos as $event) $this->eventPublisher->publish($event);
    }
}
