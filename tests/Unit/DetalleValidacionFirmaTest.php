<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DetalleValidacionFirma;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;

function detalleFirmaValida(array $certificado = []): DetalleValidacionFirma
{
    return new DetalleValidacionFirma(
        resultado: ResultadoValidacionFirma::Firmado,
        integridadCriptografica: true,
        documentoCompletoFirmado: true,
        contenidoOficialCoincide: true,
        certificadoVigente: true,
        certificadoConfiable: true,
        certificado: [
            'nombre' => 'Firmante de prueba',
            'tipo_firma' => 'ETSI.CAdES.detached',
            ...$certificado,
        ],
    );
}

test('acepta una firma CAdES íntegra vigente y confiable', function (): void {
    expect(detalleFirmaValida()->esAceptable(true))->toBeTrue();
});

test('rechaza una firma de formato distinto aunque sea criptográficamente válida', function (): void {
    expect(detalleFirmaValida(['tipo_firma' => 'adbe.pkcs7.detached'])->esAceptable(false))->toBeFalse();
});

test('en producción rechaza certificados cuya cadena no es confiable', function (): void {
    $detalle = new DetalleValidacionFirma(
        resultado: ResultadoValidacionFirma::Firmado,
        integridadCriptografica: true,
        documentoCompletoFirmado: true,
        contenidoOficialCoincide: true,
        certificadoVigente: true,
        certificadoConfiable: false,
        certificado: ['tipo_firma' => 'ETSI.CAdES.detached'],
    );

    expect($detalle->esAceptable(true))->toBeFalse()
        ->and($detalle->esAceptable(false))->toBeTrue();
});
