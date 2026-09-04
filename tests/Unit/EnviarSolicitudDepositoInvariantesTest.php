<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\SolicitudFirmadaPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\MatrizEspeciesRequeridaException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryMatrizEspeciesRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemorySolicitudDepositoRepository;

/** @return array{0: EnviarSolicitudDepositoHandler, 1: InMemorySolicitudDepositoRepository, 2: InMemoryMatrizEspeciesRepository, 3: object} */
function prepararEnvioDepositoConFirma(): array
{
    $solicitudes = new InMemorySolicitudDepositoRepository;
    $matrices = new InMemoryMatrizEspeciesRepository;
    $notificaciones = new class implements NotificacionCuratoriaPort
    {
        public int $solicitudesPorRevisar = 0;

        public function notificarIntervencionRequerida(string $solicitudId, string $investigadorId): string
        {
            return 'curador-prueba';
        }

        public function notificarNuevaSolicitudPorRevisar(string $solicitudId): string
        {
            $this->solicitudesPorRevisar++;

            return 'curador-prueba';
        }

        public function notificarLoteRecibidoParaActa(string $solicitudId, string $receptorId, bool $conObservaciones): string
        {
            return 'curador-prueba';
        }

        public function notificarDecisionDocumentalAOtrosCuradores(
            string $solicitudId,
            string $curadorQueDecideId,
            string $decision,
            ?string $motivo = null,
        ): string {
            return 'curador-prueba';
        }
    };

    $handler = new EnviarSolicitudDepositoHandler(
        repo: $solicitudes,
        transactionManager: new PassThroughTransactionManagerAdapter,
        eventPublisher: new FakeEventPublisherAdapter,
        notificacionCuratoria: $notificaciones,
        solicitudFirmada: new class implements SolicitudFirmadaPort
        {
            public function estaFirmada(string $solicitudId): bool
            {
                return true;
            }
        },
        matrizRepo: $matrices,
    );

    return [$handler, $solicitudes, $matrices, $notificaciones];
}

function crearSolicitudBorrador(InMemorySolicitudDepositoRepository $solicitudes): SolicitudDeposito
{
    $solicitud = SolicitudDeposito::crear(
        id: $solicitudes->nextIdentity(),
        numero: $solicitudes->nextNumero(),
        investigadorId: 'depositante-prueba',
        tipoTramite: 'Depósito',
    );
    $solicitudes->guardar($solicitud);

    return $solicitud;
}

test('el envío firmado también exige una matriz asociada', function (): void {
    [$handler, $solicitudes, , $notificaciones] = prepararEnvioDepositoConFirma();
    $solicitud = crearSolicitudBorrador($solicitudes);

    expect(fn () => $handler(new EnviarSolicitudDepositoInput((string) $solicitud->id())))
        ->toThrow(MatrizEspeciesRequeridaException::class);

    expect($solicitudes->buscarPorId($solicitud->id())->estado())
        ->toBe(EstadoSolicitudDeposito::EnBorrador)
        ->and($notificaciones->solicitudesPorRevisar)->toBe(0);
});

test('ninguna matriz vacía o con registros pendientes puede enviarse', function (): void {
    foreach (['sin_registros', 'registro_pendiente'] as $caso) {
        [$handler, $solicitudes, $matrices, $notificaciones] = prepararEnvioDepositoConFirma();
        $solicitud = crearSolicitudBorrador($solicitudes);
        $matriz = MatrizEspecies::crear(
            id: $matrices->nextIdentity(),
            solicitudId: (string) $solicitud->id(),
            camposDwCPresentes: ['scientificName' => true],
            tipoTramite: $solicitud->tipoTramite(),
        );
        if ($caso === 'registro_pendiente') {
            $matriz->agregarRegistroEspecimen('Nombre pendiente');
        }
        $matrices->guardar($matriz);

        expect(fn () => $handler(new EnviarSolicitudDepositoInput((string) $solicitud->id())))
            ->toThrow(MatrizEspeciesRequeridaException::class);
        expect($solicitudes->buscarPorId($solicitud->id())->estado())
            ->toBe(EstadoSolicitudDeposito::EnBorrador)
            ->and($notificaciones->solicitudesPorRevisar)->toBe(0);
    }
});

test('una matriz con todos sus registros resueltos permite el envío firmado', function (): void {
    [$handler, $solicitudes, $matrices, $notificaciones] = prepararEnvioDepositoConFirma();
    $solicitud = crearSolicitudBorrador($solicitudes);
    $matriz = MatrizEspecies::crear(
        id: $matrices->nextIdentity(),
        solicitudId: (string) $solicitud->id(),
        camposDwCPresentes: ['scientificName' => true],
        tipoTramite: $solicitud->tipoTramite(),
    );
    $registro = $matriz->agregarRegistroEspecimen('Danaus plexippus');
    $matriz->validarRegistroCatalogado($registro);
    $matrices->guardar($matriz);

    $salida = $handler(new EnviarSolicitudDepositoInput((string) $solicitud->id()));

    expect($salida->estado)->toBe(EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria)
        ->and($notificaciones->solicitudesPorRevisar)->toBe(1);
});
