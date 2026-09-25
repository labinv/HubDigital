<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recepciones.catalogo_grupos_invertebrados')) {
            return;
        }

        // «Otro invertebrado» es una categoría operativa, no un taxón de GBIF.
        DB::table('recepciones.catalogo_grupos_invertebrados')
            ->where('codigo', 'OTRO_INVERTEBRADO')->update(['activo' => false, 'updated_at' => now()]);

        $ahora = now();
        $grupos = [
            ['BRYOZOA', 'Briozoos', 'phylum', 125],
            ['BRACHIOPODA', 'Braquiópodos', 'phylum', 130],
            ['ROTIFERA', 'Rotíferos', 'phylum', 135],
            ['NEMERTEA', 'Nemertinos', 'phylum', 140],
            ['TARDIGRADA', 'Tardígrados', 'phylum', 145],
        ];
        DB::table('recepciones.catalogo_grupos_invertebrados')->insertOrIgnore(array_map(
            static fn (array $grupo): array => [
                'codigo' => $grupo[0], 'nombre' => $grupo[1],
                'rango_referencia' => $grupo[2], 'orden_visual' => $grupo[3],
                'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
            ],
            $grupos,
        ));
    }

    public function down(): void
    {
        // No elimina ni reactiva grupos porque pueden haber sido administrados por curaduría.
    }
};
