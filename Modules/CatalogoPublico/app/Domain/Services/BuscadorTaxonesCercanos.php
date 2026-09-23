<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Services;

use Illuminate\Support\Str;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesParaArbolPort;

final readonly class BuscadorTaxonesCercanos
{
    public function __construct(private ProveedorEspecimenesParaArbolPort $proveedor) {}

    /** @return list<string> */
    public function sugerir(string $consulta, int $limite = 3): array
    {
        preg_match_all('/\b([A-Z][a-z]{2,}(?:\s+[a-z]{3,})?)\b/u', Str::ascii($consulta), $encontrados);
        $nombres = array_values(array_filter($encontrados[1] ?? [], static function (string $nombre): bool {
            return ! in_array(Str::lower(strtok($nombre, ' ')), ['que', 'cual', 'donde', 'como', 'quiero', 'busco', 'tengo'], true);
        }));
        if ($nombres === []) {
            return [];
        }

        $buscado = end($nombres);
        $candidatos = [];
        foreach ($this->proveedor->obtenerTodos() as $especimen) {
            if (! $especimen->esDivulgableEnArbol()) {
                continue;
            }
            foreach ([$especimen->jerarquia->scientificName, $especimen->jerarquia->genus] as $nombre) {
                $puntuacion = $this->similitud($buscado, $nombre);
                if ($puntuacion >= 0.55 && Str::lower($buscado) !== Str::lower($nombre)) {
                    $candidatos[$nombre] = max($candidatos[$nombre] ?? 0, $puntuacion);
                }
            }
        }
        arsort($candidatos);

        return array_slice(array_keys($candidatos), 0, $limite);
    }

    private function similitud(string $izquierda, string $derecha): float
    {
        $a = $this->trigramas($izquierda);
        $b = $this->trigramas($derecha);
        if ($a === [] || $b === []) {
            return 0;
        }

        return 2 * count(array_intersect($a, $b)) / (count($a) + count($b));
    }

    /** @return list<string> */
    private function trigramas(string $texto): array
    {
        $normal = '  '.Str::lower(Str::ascii(trim($texto))).'  ';
        $triples = [];
        for ($i = 0, $max = strlen($normal) - 2; $i < $max; $i++) {
            $triples[] = substr($normal, $i, 3);
        }

        return array_values(array_unique($triples));
    }
}
