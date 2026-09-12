<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

test('permite registrar manualmente el origen de una donación', function (): void {
    $solicitud = SolicitudDeposito::crear(
        id: SolicitudDepositoId::generate(),
        numero: NumeroSolicitudDeposito::fromSecuencia(1),
        investigadorId: 'depositante-qa',
        tipoTramite: 'Donación',
    );

    $solicitud->completarDatoFaltante('Origen Donación', 'Colección didáctica sintética QA');

    expect($solicitud->origenDonacion())->toBe('Colección didáctica sintética QA');
});
