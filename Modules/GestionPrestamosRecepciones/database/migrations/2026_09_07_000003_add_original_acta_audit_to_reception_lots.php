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
            $table->char('acta_original_sha256', 64)->nullable()->after('acta_recepcion');
            $table->unsignedInteger('acta_original_version')->default(0)->after('acta_original_sha256');
            $table->timestampTz('acta_original_materializada_en')->nullable()->after('acta_original_version');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.recepcion_lotes', function (Blueprint $table): void {
            $table->dropColumn(['acta_original_sha256', 'acta_original_version', 'acta_original_materializada_en']);
        });
    }
};
