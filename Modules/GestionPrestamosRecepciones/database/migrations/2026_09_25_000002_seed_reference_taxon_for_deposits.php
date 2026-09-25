<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.taxones')) {
            return;
        }

        // Especie aceptada: GBIF, https://www.gbif.org/taxon/JMBV.
        DB::table('taxonomia.taxones')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'nombre_cientifico' => 'Atta cephalotes',
            'rango' => 'especie',
            'autor' => '(Linnaeus, 1758)',
            'anio_descripcion' => 1758,
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Curaduría puede haber administrado el taxón; no se elimina automáticamente.
    }
};
