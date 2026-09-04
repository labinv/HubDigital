<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Services\AnalizadorDocumentoAmbiental;

it('clasifica una guía por el contenido y extrae sus códigos sin depender del nombre del archivo', function (): void {
    $texto = <<<'TXT'
    MINISTERIO DEL AMBIENTE
    GUÍA DE MOVILIZACIÓN DE ESPECÍMENES DE VIDA SILVESTRE No. 012-2026-MAE-DZ7-OTMA-UBVS
    Fecha de emisión: 30 de junio de 2026
    Fecha de movilización: 1 de julio de 2026
    Válido hasta: 3 de julio de 2026
    Autorización de recolección de vida silvestre Nro. 012-2026 IC-FLO/FAU/DZL/OTM//MAE
    Responsable de la movilización: Ana Pérez
    Tipo de transporte: terrestre Placa: ABC-1234
    DATOS DE LAS MUESTRAS
    PMB-01 Ephemeroptera 25 individuos 1 lote
    PMB-02 Coleoptera 12 individuos 1 lote
    Documento firmado electrónicamente
    TXT;

    $resultado = (new AnalizadorDocumentoAmbiental)->analizar($texto);

    expect($resultado['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION)
        ->and($resultado['numero_documento'])->toBe('012-2026-MAE-DZ7-OTMA-UBVS')
        ->and($resultado['numero_autorizacion'])->toBe('012-2026 IC-FLO/FAU/DZL/OTM//MAE')
        ->and($resultado['valido_desde'])->toBe('2026-07-01')
        ->and($resultado['valido_hasta'])->toBe('2026-07-03')
        ->and($resultado['codigos_muestra'])->toContain('PMB-01', 'PMB-02')
        ->and($resultado['codigos_muestra'])->not->toContain('ABC-1234')
        ->and($resultado['numero_individuos'])->toBe(37)
        ->and($resultado['requiere_confirmacion_humana'])->toBeTrue()
        ->and($resultado['puntajes'][AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION]['evidencias'])->not->toBeEmpty()
        ->and($resultado['numero_lotes'])->toBe(2);
});

it('normaliza separaciones del PDF real y limita los códigos al cuadro de muestras', function (): void {
    $resultado = (new AnalizadorDocumentoAmbiental)->analizar(<<<'TXT'
    MINISTERIO DEL AMBIENTE Y ENERGÍA
    GUÍA DE MOVILIZACIÓN DE ESPECÍMENES DE FLORA Y FAUNA SILVESTRE
    No. 012-2026-MAE-DZ7-OTMA-UBVS
    Fecha de emisión: 30 de junio del 2026
    Fecha de movilización: 01 de julio del 2026 Válido hasta: 03 de julio del 2026
    Representante Legal de TERRASOLUTION S.A.S, con RUC 1591728293001
    AUTORIZACIÓN DE RECOLECCIÓN DE VIDA SILVESTRE Nº 0 12-2026 IC -
    FLO/FAU/DZL/OTM//MAE
    Responsable de la movilización. Tipo de transporte: Particular. Placa: OBB4893
    DATOS DE LAS MUESTRAS
    1 La Avelina Beatriz PMB-01 Ephemeroptera Muestra preservada 1 lote
    2 La Avelina Beatriz PMB-02 Coleoptera Muestra preservada 1 lote
    3 La Avelina Beatriz PMB-03 Trichoptera Muestra preservada 1 lote
    4 La Avelina Beatriz PMB-04 Odonata Muestra preservada 1 lote
    OBSERVACIONES: material para investigación.
    Documento firmado electrónicamente
    TXT);

    expect($resultado['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION)
        ->and($resultado['numero_autorizacion'])->toBe('012-2026 IC-FLO/FAU/DZL/OTM//MAE')
        ->and($resultado['codigos_muestra'])->toBe(['PMB-01', 'PMB-02', 'PMB-03', 'PMB-04'])
        ->and($resultado['numero_lotes'])->toBe(4)
        ->and($resultado['evidencias_campos']['numero_documento'] ?? null)->not->toBeNull();
});

it('clasifica una autorización ambiental y detecta la relación con la guía', function (): void {
    $analizador = new AnalizadorDocumentoAmbiental;
    $autorizacion = $analizador->analizar(<<<'TXT'
    MINISTERIO DEL AMBIENTE
    Oficio Nro. MAE-DZ8-2026-1691-OF
    Tena, 08 de junio de 2026
    AUTORIZACIÓN DE RECOLECCIÓN DE VIDA SILVESTRE Nro. 026-2026-OTOR-DZ8-MAE
    Se otorga la autorización a LABCESTTA S.A. para el proyecto denominado "Mechero B60".
    Grupo biológico a estudiar: Insectos. Directora Zonal.
    Deberá solicitar oportunamente la guía de movilización.
    Documento firmado electrónicamente
    TXT);
    $guia = $analizador->analizar(<<<'TXT'
    MINISTERIO DEL AMBIENTE
    GUÍA DE MOVILIZACIÓN DE ESPECÍMENES DE VIDA SILVESTRE No. 099-2026-MAE-DZ8-OTMA
    Fecha de movilización: 10 de junio de 2026
    Válido hasta: 12 de junio de 2026
    Autorización de recolección de vida silvestre Nro. 026-2026-OTOR-DZ8-MAE
    Responsable de la movilización. Tipo de transporte: terrestre. Placa: ABC-1234.
    DATOS DE LAS MUESTRAS LAB-01
    Documento firmado electrónicamente
    TXT);

    $expediente = $analizador->validarExpediente([
        AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION => $autorizacion,
        AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION => $guia,
    ]);

    expect($autorizacion['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION)
        ->and($autorizacion['numero_documento'])->toBe('MAE-DZ8-2026-1691-OF')
        ->and($autorizacion['numero_autorizacion'])->toBe('026-2026-OTOR-DZ8-MAE')
        ->and($expediente['estado'])->not->toBe('rechazado');
});

it('rechaza documentos válidos individualmente cuando pertenecen a expedientes distintos', function (): void {
    $analizador = new AnalizadorDocumentoAmbiental;

    $resultado = $analizador->validarExpediente([
        AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION => [
            'tipo_detectado' => AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION,
            'numero_autorizacion' => '026-2026-OTOR-DZ8-MAE',
            'errores' => [], 'advertencias' => [],
        ],
        AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION => [
            'tipo_detectado' => AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION,
            'numero_autorizacion' => '012-2026 IC-FLO/FAU/DZL/OTM//MAE',
            'errores' => [], 'advertencias' => [],
        ],
    ]);

    expect($resultado['estado'])->toBe('rechazado')
        ->and(implode(' ', $resultado['errores']))->toContain('pero el oficio aportado concede');
});

it('rechaza contenido genérico aunque el archivo haya sido cargado en una casilla regulatoria', function (): void {
    $resultado = (new AnalizadorDocumentoAmbiental)->analizar(
        'Informe mensual de actividades administrativas sin permisos ni guías ambientales.'
    );

    expect($resultado['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::DESCONOCIDO)
        ->and($resultado['estado'])->toBe('rechazado')
        ->and($resultado['errores'])->not->toBeEmpty();
});
