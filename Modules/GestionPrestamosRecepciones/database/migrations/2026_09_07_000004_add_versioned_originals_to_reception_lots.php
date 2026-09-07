<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.recepcion_lotes', function (Blueprint $table): void {
            $table->uuid('acta_original_referencia')->nullable()->unique()->after('acta_original_version');
            $table->string('acta_original_ruta')->nullable()->after('acta_original_referencia');
            $table->jsonb('acta_original_historial')->nullable()->after('acta_original_materializada_en');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.recepcion_lotes', function (Blueprint $table): void {
            $table->dropUnique(['acta_original_referencia']);
            $table->dropColumn(['acta_original_referencia', 'acta_original_ruta', 'acta_original_historial']);
        });
    }
};
