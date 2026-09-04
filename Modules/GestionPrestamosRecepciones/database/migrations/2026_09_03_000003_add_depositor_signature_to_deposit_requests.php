<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->string('solicitud_firmada_ruta')->nullable();
            $table->char('solicitud_firmada_sha256', 64)->nullable();
            $table->timestamp('solicitud_firmada_en')->nullable();
            $table->jsonb('solicitud_firma_metadata')->default('{}');
            $table->unsignedSmallInteger('solicitud_documento_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->dropColumn([
                'solicitud_firmada_ruta',
                'solicitud_firmada_sha256',
                'solicitud_firmada_en',
                'solicitud_firma_metadata',
                'solicitud_documento_version',
            ]);
        });
    }
};
