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
            $table->uuid('iniciado_por')->nullable()->after('firmada_en');
            $table->timestampTz('iniciado_en')->nullable()->after('iniciado_por');
            $table->uuid('suspendido_por')->nullable()->after('iniciado_en');
            $table->uuid('reanudado_por')->nullable()->after('suspendido_por');
            $table->timestampTz('reanudado_en')->nullable()->after('reanudado_por');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.recepcion_lotes', function (Blueprint $table): void {
            $table->dropColumn(['iniciado_por', 'iniciado_en', 'suspendido_por', 'reanudado_por', 'reanudado_en']);
        });
    }
};
