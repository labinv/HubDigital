<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Support\Str;
use Modules\CatalogoPublico\Application\Ports\ClasificadorIntencionPort;
use Modules\CatalogoPublico\Domain\ValueObjects\EntidadesExtraidas;
use Modules\CatalogoPublico\Domain\ValueObjects\IntencionConsulta;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

final class ClasificadorCatalogoLocal implements ClasificadorIntencionPort
{
    public function clasificar(string $pregunta): IntencionConsulta
    {
        $texto = Str::lower(Str::ascii($pregunta));
        $rangos = [
            'familia' => RangoTaxonomico::Family, 'familias' => RangoTaxonomico::Family,
            'genero' => RangoTaxonomico::Genus, 'generos' => RangoTaxonomico::Genus,
            'especie' => RangoTaxonomico::Species, 'especies' => RangoTaxonomico::Species,
            'orden' => RangoTaxonomico::Order, 'ordenes' => RangoTaxonomico::Order,
            'clase' => RangoTaxonomico::Class_, 'clases' => RangoTaxonomico::Class_,
            'filo' => RangoTaxonomico::Phylum, 'filos' => RangoTaxonomico::Phylum,
        ];

        if (preg_match('/\b(top|ranking|cuant[oa]s?|mas|mayor|menos|numero|total|porcentaje|proporcion|distribucion por)\b/', $texto)) {
            foreach ($rangos as $palabra => $rango) {
                if (preg_match('/\b'.preg_quote($palabra, '/').'\b/', $texto)) {
                    preg_match('/\b(?:top|los|las)\s+(\d{1,2})\b/', $texto, $cantidad);

                    return IntencionConsulta::ranking($rango, min(20, max(1, (int) ($cantidad[1] ?? 5))));
                }
            }
            if (str_contains($texto, 'registros') && ! preg_match('/\bde\s+[A-Z][a-z]+/', $pregunta)) {
                return IntencionConsulta::ranking(RangoTaxonomico::Family);
            }
        }

        if (preg_match('/\b([A-Z]{2,8}-\d{1,12})\b/i', $pregunta, $id)) {
            return IntencionConsulta::dentroDelDominio(EntidadesExtraidas::desde(occurrenceID: $id[1]));
        }
        preg_match_all('/\b([A-Z][a-z]{2,}\s+[a-z]{2,})\b/u', Str::ascii($pregunta), $especies);
        foreach (array_reverse($especies[1] ?? []) as $nombre) {
            $primera = Str::lower(strtok($nombre, ' '));
            if (! in_array($primera, ['que', 'cual', 'donde', 'como', 'quiero', 'busco', 'tengo', 'cuantos', 'cuales'], true)) {
                return IntencionConsulta::dentroDelDominio(EntidadesExtraidas::desde(especie: $nombre));
            }
        }
        if (preg_match('/\b(?:genero|de|sobre)\s+([A-Z][a-z]{2,})\b/u', Str::ascii($pregunta), $genero)
            && ! in_array(Str::lower($genero[1]), ['ecuador', 'portal', 'coleccion'], true)) {
            return IntencionConsulta::dentroDelDominio(EntidadesExtraidas::desde(genero: $genero[1]));
        }

        return IntencionConsulta::fueraDelDominio();
    }
}
