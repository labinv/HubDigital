<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.taxones')) {
            return;
        }

        $anterior = DB::table('taxonomia.taxones')
            ->where('nombre_cientifico', 'Atta cephalotes')
            ->where('rango', 'species')
            ->where('autor', '(Linnaeus, 1758)')
            ->first();
        if ($anterior === null) {
            return;
        }

        $vigente = DB::table('taxonomia.taxones')
            ->where('nombre_cientifico', 'Atta cephalotes')
            ->where('rango', 'especie')
            ->first();
        if ($vigente === null) {
            DB::table('taxonomia.taxones')->where('id', $anterior->id)
                ->update(['rango' => 'especie', 'updated_at' => now()]);

            return;
        }

        if (Schema::hasTable('taxonomia.especimenes')) {
            DB::table('taxonomia.especimenes')->where('taxon_id', $anterior->id)
                ->update(['taxon_id' => $vigente->id]);
        }
        if (Schema::hasTable('taxonomia.identificaciones')) {
            DB::table('taxonomia.identificaciones')->where('taxon_id', $anterior->id)
                ->update(['taxon_id' => $vigente->id]);
        }
        DB::table('taxonomia.taxones')->where('padre_id', $anterior->id)
            ->update(['padre_id' => $vigente->id]);
        DB::table('taxonomia.taxones')->where('id', $anterior->id)->delete();
    }

    public function down(): void
    {
        // La forma inglesa no se vuelve a introducir.
    }
};
