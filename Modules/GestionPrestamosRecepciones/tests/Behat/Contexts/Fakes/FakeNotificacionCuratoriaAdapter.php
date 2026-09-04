<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes;

use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;

final class FakeNotificacionCuratoriaAdapter implements NotificacionCuratoriaPort
{
    private const CURADOR_ID = 'curador-fake-001';

    /** @var list<array{solicitudId: string, receptorId: string, conObservaciones: bool}> */
    private array $alertasActa = [];

    public function notificarIntervencionRequerida(string $solicitudId, string $investigadorId): string
    {
        return self::CURADOR_ID;
    }

    public function notificarNuevaSolicitudPorRevisar(string $solicitudId): string
    {
        return self::CURADOR_ID;
    }

    public function notificarLoteRecibidoParaActa(string $solicitudId, string $receptorId, bool $conObservaciones): string
    {
        $this->alertasActa[] = compact('solicitudId', 'receptorId', 'conObservaciones');

        return self::CURADOR_ID;
    }

    /** @return list<array{solicitudId: string, receptorId: string, conObservaciones: bool}> */
    public function alertasActa(): array
    {
        return $this->alertasActa;
    }

    public function notificarDecisionDocumentalAOtrosCuradores(
        string $solicitudId,
        string $curadorQueDecideId,
        string $decision,
        ?string $motivo = null,
    ): string {
        return self::CURADOR_ID;
    }
}
