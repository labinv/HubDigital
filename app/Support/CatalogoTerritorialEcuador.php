<?php

declare(strict_types=1);

namespace App\Support;

/** Catálogo CONALI 2020 de provincias y cantones de Ecuador. */
final class CatalogoTerritorialEcuador
{
    /** @return array<int, array{codigo:string,nombre:string,cantones:array}> */
    public static function provincias(): array
    {
        $datos = json_decode(file_get_contents(resource_path('data/ecuador-provincias-cantones.json')), true, 512, JSON_THROW_ON_ERROR);

        return $datos['provincias'];
    }

    /** @return array<int, array{codigo:string,nombre:string}> */
    public static function cantones(string $provincia): array
    {
        foreach (self::provincias() as $item) {
            if ($item['nombre'] === $provincia) {
                return $item['cantones'];
            }
        }

        return [];
    }

    /** @return list<string> */
    public static function parroquias(string $provincia, string $canton): array
    {
        $datos = json_decode(file_get_contents(resource_path('data/ecuador-parroquias.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($datos['provincias'] as $item) {
            if ($item['nombre'] !== $provincia) {
                continue;
            }
            foreach ($item['cantones'] as $municipio) {
                if ($municipio['nombre'] === $canton) {
                    return $municipio['parroquias'];
                }
            }
        }

        return [];
    }

    public static function contiene(string $provincia, string $canton): bool
    {
        foreach (self::cantones($provincia) as $item) {
            if ($item['nombre'] === $canton) {
                return true;
            }
        }

        return false;
    }
}
