<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recepciones.solicitudes_deposito')) {
            return;
        }

        DB::table('recepciones.solicitudes_deposito')
            ->where('paso_actual', '>=', 5)
            ->update(['paso_actual' => DB::raw('paso_actual + 2')]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('recepciones.solicitudes_deposito')) {
            return;
        }

        DB::table('recepciones.solicitudes_deposito')
            ->where('paso_actual', '>=', 5)
            ->update(['paso_actual' => DB::raw('CASE WHEN paso_actual IN (5, 6) THEN 4 ELSE paso_actual - 2 END')]);
    }
};
