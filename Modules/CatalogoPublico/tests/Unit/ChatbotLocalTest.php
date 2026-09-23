<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Domain\ValueObjects\ContextoLLM;
use Modules\CatalogoPublico\Domain\ValueObjects\DatosEspecimenParaContexto;
use Modules\CatalogoPublico\Domain\ValueObjects\EstadisticaTaxon;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;
use Modules\CatalogoPublico\Infrastructure\Adapters\ClasificadorCatalogoLocal;
use Modules\CatalogoPublico\Infrastructure\Adapters\GeneradorCatalogoExacto;

test('clasifica ranking y nombre cientifico sin depender de una API', function (): void {
    $clasificador = new ClasificadorCatalogoLocal;
    $ranking = $clasificador->clasificar('¿Cuál es la familia con más registros?');
    $especie = $clasificador->clasificar('¿Dónde encontraron Dichotomius satanas?');

    expect($ranking->rangoAgregacion)->toBe(RangoTaxonomico::Family)
        ->and($especie->entidades->especie)->toBe('Dichotomius satanas');
});

test('calcula porcentajes y provincias a partir de datos publicados', function (): void {
    $generador = new GeneradorCatalogoExacto;
    $ranking = ContextoLLM::conRanking(
        pregunta: '¿Qué porcentaje corresponde a la familia con más registros?',
        rango: RangoTaxonomico::Family,
        estadisticas: [EstadisticaTaxon::crear(RangoTaxonomico::Family, 'Formicidae', 3)],
        totalUnicosEnRango: 2,
        totalRegistrosEnRango: 4,
    );
    $lugares = ContextoLLM::conEspecimenes('¿En qué provincias aparece Atta?', [
        DatosEspecimenParaContexto::crear('EPN-1', ['stateProvince' => 'Pichincha']),
        DatosEspecimenParaContexto::crear('EPN-2', ['stateProvince' => 'Pichincha']),
    ]);

    expect($generador->generar($ranking))->toContain('75%')
        ->and($generador->generar($lugares))->toContain('Pichincha: 2');
});
