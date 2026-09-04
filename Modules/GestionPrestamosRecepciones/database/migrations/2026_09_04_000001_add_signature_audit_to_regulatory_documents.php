<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.documentos_regulatorios', function (Blueprint $table): void {
            $table->string('firma_estado', 30)->nullable()->after('estado_validacion');
            $table->timestampTz('firma_verificada_en')->nullable()->after('firma_estado');
            $table->index(
                ['solicitud_id', 'firma_estado'],
                'doc_reg_solicitud_firma_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.documentos_regulatorios', function (Blueprint $table): void {
            $table->dropIndex('doc_reg_solicitud_firma_idx');
            $table->dropColumn(['firma_estado', 'firma_verificada_en']);
        });
    }
};
