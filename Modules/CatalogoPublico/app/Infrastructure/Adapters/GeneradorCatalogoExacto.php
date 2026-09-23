<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Modules\CatalogoPublico\Application\Ports\GeneradorRespuestaChatBotPort;
use Modules\CatalogoPublico\Domain\ValueObjects\ChatBotMensajes;
use Modules\CatalogoPublico\Domain\ValueObjects\ContextoLLM;

final class GeneradorCatalogoExacto implements GeneradorRespuestaChatBotPort
{
    public function generar(ContextoLLM $contexto): string
    {
        if ($contexto->estaVacio()) {
            return ChatBotMensajes::SIN_RESULTADOS;
        }

        if ($contexto->esRanking()) {
            if (preg_match('/porcent|proporci/ui', $contexto->pregunta)
                && $contexto->totalRegistrosEnRango > 0) {
                $primero = $contexto->estadisticas[0];
                $porcentaje = round(100 * $primero->cantidadRegistros / $contexto->totalRegistrosEnRango, 1);

                return "{$primero->nombre} representa {$porcentaje}% de los {$contexto->totalRegistrosEnRango} registros publicados con ese rango taxonómico.";
            }
            $filas = array_map(
                static fn ($fila): string => "{$fila->nombre}: {$fila->cantidadRegistros}",
                $contexto->estadisticas,
            );

            return 'En los registros publicados de la colección, el total de taxones distintos en este rango es '
                .$contexto->totalUnicosEnRango.'. Los primeros son: '.implode('; ', $filas).'.';
        }

        if (preg_match('/provincia|distribuci/ui', $contexto->pregunta)) {
            $conteos = [];
            foreach ($contexto->especimenes as $especimen) {
                $provincia = $especimen->camposVisibles['stateProvince'] ?? null;
                if (is_string($provincia) && $provincia !== '') {
                    $conteos[$provincia] = ($conteos[$provincia] ?? 0) + 1;
                }
            }
            if ($conteos !== []) {
                arsort($conteos);
                $filas = [];
                foreach ($conteos as $provincia => $total) {
                    $filas[] = "{$provincia}: {$total}";
                }

                return 'En los registros publicados encontrados, la distribución por provincia es: '.implode('; ', $filas).'.';
            }
        }

        $filas = [];
        foreach (array_slice($contexto->especimenes, 0, 10) as $especimen) {
            $campos = $especimen->camposVisibles;
            $detalle = array_filter([
                $campos['scientificName'] ?? null,
                $campos['family'] ?? null,
                $campos['country'] ?? null,
                $campos['localityName'] ?? null,
            ]);
            $filas[] = $especimen->occurrenceID.($detalle ? ' — '.implode(', ', $detalle) : '');
        }

        return 'Encontré '.count($contexto->especimenes).' registros publicados en el catálogo: '
            .implode('; ', $filas)
            .(count($contexto->especimenes) > 10 ? '. Se muestran los primeros 10.' : '.');
    }
}
