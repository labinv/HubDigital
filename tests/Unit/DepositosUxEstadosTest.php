<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\RecepcionFisicaLote;

test('la recepción distingue criterios pendientes de resultados comprobados', function (): void {
    $componente = new RecepcionFisicaLote;
    $tienePendientes = Closure::bind(
        fn (): bool => $this->tieneCriteriosPendientesOInvalidos(),
        $componente,
        RecepcionFisicaLote::class,
    );

    expect($tienePendientes())->toBeTrue();

    $componente->estadoCriterio = [
        0 => 'conforme',
        1 => 'conforme',
        2 => 'no_conforme',
        3 => 'conforme',
    ];

    expect($tienePendientes())->toBeFalse();

    $componente->estadoCriterio[2] = 'valor_invalido';

    expect($tienePendientes())->toBeTrue();
});

test('las vistas comunican estados documentales y el formato real del QR', function (): void {
    $raiz = dirname(__DIR__, 2);
    $documentos = file_get_contents($raiz.'/Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito/paso-documentos.blade.php');
    $detalle = file_get_contents($raiz.'/Modules/GestionPrestamosRecepciones/resources/views/investigador/detalle-deposito.blade.php');
    $recepcion = file_get_contents($raiz.'/Modules/GestionPrestamosRecepciones/resources/views/curador/recepcion-fisica-lote.blade.php');

    expect($documentos)
        ->toContain('Esta modalidad genera internamente la solicitud y sus declaraciones; no necesitas adjuntar archivos en este paso.')
        ->not->toContain('Cargando documentos requeridos');

    expect($detalle)
        ->toContain('Imprimir QR', 'Guardar como PDF')
        ->not->toContain('Imprimir / Guardar QR (PDF)');

    expect($recepcion)
        ->toContain('Pendiente de comprobar', 'Criterio confirmado', 'No conformidad / observación')
        ->not->toContain('wire:model="conforme.');
});
