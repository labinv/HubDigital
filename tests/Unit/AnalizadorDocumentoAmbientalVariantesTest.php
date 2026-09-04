<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Services\AnalizadorDocumentoAmbiental;

function fixtureAmbiental(string $nombre): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/documentos_ambientales/'.$nombre.'.txt');
}

it('reconoce familias versionadas de autorizaciones sin depender de una plantilla visual', function (
    string $fixture,
    string $numeroDocumento,
    string $numeroAutorizacion,
): void {
    $resultado = (new AnalizadorDocumentoAmbiental)->analizar(fixtureAmbiental($fixture));

    expect($resultado['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION)
        ->and($resultado['numero_documento'])->toBe($numeroDocumento)
        ->and($resultado['numero_autorizacion'])->toBe($numeroAutorizacion)
        ->and($resultado['decision_automatica'])->toBe('REVISION_HUMANA')
        ->and($resultado['requiere_confirmacion_humana'])->toBeTrue()
        ->and($resultado['version_reglas'])->toBe(AnalizadorDocumentoAmbiental::VERSION_REGLAS)
        ->and($resultado['evidencias_campos']['numero_documento']['fragmento'] ?? null)->not->toBeNull()
        ->and($resultado['evidencias_campos']['numero_autorizacion']['patron'] ?? null)->not->toBeNull();
})->with([
    'formato zonal tabular' => ['autorizacion_zonal_2025', '101-2025-FA-FLO/DZ5/MAATE', '101-2025-FA-FLO/DZ5/MAATE'],
    'formato SUIA con código separado' => ['autorizacion_suia_2023', 'MAATE-ARSFC-2023-9123', 'MAATE-ARSFC-2023-9123'],
    'oficio Quipux que otorga autorización' => ['autorizacion_oficio_2026', 'MAATE-DZ8-2026-9999-O', '555-2026-OTOR-DZ8-MAATE'],
]);

it('reconoce familias versionadas de guías y conserva evidencia por campo', function (
    string $fixture,
    string $numeroGuia,
    string $numeroAutorizacion,
    string $fechaInicio,
    string $fechaFin,
): void {
    $analizador = new AnalizadorDocumentoAmbiental;
    $resultado = $analizador->analizar(fixtureAmbiental($fixture));

    expect($resultado['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION)
        ->and($resultado['numero_documento'])->toBe($numeroGuia)
        ->and($resultado['numero_autorizacion'])->toBe($numeroAutorizacion)
        ->and($resultado['valido_desde'])->toBe($fechaInicio)
        ->and($resultado['valido_hasta'])->toBe($fechaFin)
        ->and($resultado['estado'])->toBe('revision')
        ->and($analizador->campoTieneEvidenciaSuficiente($resultado, 'numero_documento'))->toBeTrue()
        ->and($analizador->campoTieneEvidenciaSuficiente($resultado, 'numero_autorizacion'))->toBeTrue();
})->with([
    'guía zonal 2022' => ['guia_zonal_2022', 'MAATE-DZ2-OTE-123-2022', '321-2022-RVS-FLO-FAU-DZ2-OTE', '2022-11-15', '2022-11-18'],
    'guía zonal 2026 con ruido OCR y saltos' => ['guia_zonal_2026_ocr_mutado', '777-2026-MAE-DZ7-OTMA-UBVS', '111-2026 IC-FLO/FAU/DZL/OTM//MAE', '2026-07-01', '2026-07-03'],
    'guía SUIA 2023' => ['guia_suia_2023', '54321', 'MAATE-ARSFC-2023-9123', '2023-06-08', '2023-06-09'],
]);

it('limita los códigos de muestra a la sección probatoria incluso si cambia el formato', function (): void {
    $zonal = (new AnalizadorDocumentoAmbiental)->analizar(fixtureAmbiental('guia_zonal_2022'));
    $ocr = (new AnalizadorDocumentoAmbiental)->analizar(fixtureAmbiental('guia_zonal_2026_ocr_mutado'));

    expect($zonal['codigos_muestra'])->toBe(['QA1', 'QA2'])
        ->and($zonal['evidencias_codigos_muestra'])->toHaveCount(2)
        ->and($ocr['codigos_muestra'])->toBe(['PMB-01', 'PMB-02'])
        ->and($ocr['codigos_muestra'])->not->toContain('ABC-9999')
        ->and($ocr['numero_individuos'])->toBe(37)
        ->and($ocr['numero_lotes'])->toBe(2)
        ->and($ocr['evidencias_campos']['numero_individuos']['fragmento'] ?? null)->not->toBeNull();
});

it('rechaza un archivo incorrecto aunque mencione permisos y movilización', function (): void {
    $resultado = (new AnalizadorDocumentoAmbiental)->analizar(<<<'TXT'
    INFORME ADMINISTRATIVO DE PRUEBA
    La reunión trató sobre permisos, recolección y guías de movilización.
    No constituye autorización, guía ni acto emitido por la autoridad ambiental.
    TXT);

    expect($resultado['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::DESCONOCIDO)
        ->and($resultado['estado'])->toBe('rechazado')
        ->and($resultado['autocompletado_habilitado'])->toBeFalse()
        ->and($resultado['numero_documento'])->toBeNull();
});

it('rechaza códigos contradictorios para el mismo campo y nunca los autocompleta', function (): void {
    $resultado = (new AnalizadorDocumentoAmbiental)->analizar(<<<'TXT'
    AUTORIZACIÓN DE RECOLECCIÓN DE VIDA SILVESTRE N.º 111-2026-FA-FLO-DZ8-MAATE
    El Ministerio del Ambiente y Energía otorga la autorización a una organización de prueba.
    AUTORIZACIÓN DE RECOLECCIÓN DE VIDA SILVESTRE N.º 999-2026-FA-FLO-DZ8-MAATE
    Vigencia de la Autorización de Recolección: desde 01/01/2026 hasta 31/12/2026.
    ÁREA GEOGRÁFICA QUE CUBRE LA RECOLECCIÓN.
    Documento firmado electrónicamente.
    TXT);

    expect($resultado['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION)
        ->and($resultado['estado'])->toBe('rechazado')
        ->and($resultado['numero_autorizacion'])->toBeNull()
        ->and($resultado['autocompletado_habilitado'])->toBeFalse()
        ->and($resultado['candidatos_ambiguos']['numero_autorizacion'] ?? [])->toHaveCount(2);
});

it('no confunde la vigencia desde-hasta con una ruta de movilización', function (): void {
    $resultado = (new AnalizadorDocumentoAmbiental)->analizar(fixtureAmbiental('autorizacion_zonal_2025'));

    expect($resultado['valido_desde'])->toBe('2025-07-21')
        ->and($resultado['valido_hasta'])->toBe('2025-10-21')
        ->and($resultado['origen'])->toBeNull()
        ->and($resultado['destino'])->toBeNull();
});

it('bloquea el expediente ante contradicción de códigos o vigencias', function (): void {
    $analizador = new AnalizadorDocumentoAmbiental;
    $autorizacion = $analizador->analizar(fixtureAmbiental('autorizacion_zonal_2025'));
    $guia = $analizador->analizar(fixtureAmbiental('guia_zonal_2026_ocr_mutado'));
    $resultado = $analizador->validarExpediente([
        AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION => $autorizacion,
        AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION => $guia,
    ]);

    expect($resultado['estado'])->toBe('rechazado')
        ->and($resultado['decision_automatica'])->toBe('RECHAZADO')
        ->and($resultado['autocompletado_habilitado'])->toBeFalse()
        ->and(implode(' ', $resultado['errores']))->toContain('guía cita la autorización');
});

it('envía a revisión humana cuando falta evidencia para el contraste sin inventar el vínculo', function (): void {
    $resultado = (new AnalizadorDocumentoAmbiental)->validarExpediente([
        AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION => [
            'tipo_detectado' => AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION,
            'numero_autorizacion' => null,
            'errores' => [],
            'advertencias' => [],
        ],
        AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION => [
            'tipo_detectado' => AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION,
            'numero_autorizacion' => null,
            'errores' => [],
            'advertencias' => [],
        ],
    ]);

    expect($resultado['estado'])->toBe('revision')
        ->and($resultado['decision_automatica'])->toBe('REVISION_HUMANA')
        ->and(implode(' ', $resultado['advertencias']))->toContain('No fue posible contrastar');
});
