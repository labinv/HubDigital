<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\MantenerNombreTaxonomicoOriginal;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

/** Deriva a revisión curatorial una sugerencia que el depositante rechaza. */
final class MantenerNombreTaxonomicoOriginalHandler
{
    public function __construct(
        private MatrizEspeciesRepositoryInterface $matrizRepo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

    /** @throws \DomainException */
    public function __invoke(MantenerNombreTaxonomicoOriginalInput $input): MantenerNombreTaxonomicoOriginalOutput
    {
        $matriz = $this->matrizRepo->buscarPorId(MatrizEspeciesId::from($input->matrizId));

        if ($matriz === null) {
            throw new \DomainException(sprintf('No se encontró la matriz de especies con ID "%s"', $input->matrizId));
        }

        $matriz->mantenerNombreOriginal(
            $input->registroId,
            $input->motivoJustificacion,
            $input->comentarioJustificacion,
        );

        $this->transactionManager->executeTransactional(function () use ($matriz): void {
            $this->matrizRepo->guardar($matriz);

            foreach ($matriz->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        return new MantenerNombreTaxonomicoOriginalOutput(
            estadoRegistro: $matriz->estadoRegistro($input->registroId),
            estadoMatriz: $matriz->estado(),
            registroId: $input->registroId,
        );
    }
}
