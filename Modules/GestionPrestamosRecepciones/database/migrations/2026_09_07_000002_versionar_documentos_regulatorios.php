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
            $table->unsignedInteger('version')->default(1)->after('tipo_esperado');
            $table->dropUnique('doc_reg_solicitud_tipo_unique');
            $table->unique(['solicitud_id', 'tipo_esperado', 'version'], 'doc_reg_solicitud_tipo_version_unique');
            $table->unique(['solicitud_id', 'tipo_esperado', 'sha256'], 'doc_reg_solicitud_tipo_sha_unique');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.documentos_regulatorios', function (Blueprint $table): void {
            $table->dropUnique('doc_reg_solicitud_tipo_version_unique');
            $table->dropUnique('doc_reg_solicitud_tipo_sha_unique');
            $table->dropColumn('version');
            $table->unique(['solicitud_id', 'tipo_esperado'], 'doc_reg_solicitud_tipo_unique');
        });
    }
};
