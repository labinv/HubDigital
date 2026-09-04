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
            $table->uuid('recibido_por')->nullable()->after('observaciones');
            $table->timestamp('verificado_en')->nullable()->after('recibido_por');
            $table->timestamp('suspendido_en')->nullable()->after('verificado_en');
            $table->uuid('acta_generada_por')->nullable()->after('acta_recepcion');
            $table->timestamp('acta_generada_en')->nullable()->after('acta_generada_por');
            $table->jsonb('firma_metadata')->default('{}')->after('firmada_en');

            $table->foreign('recibido_por')->references('id')->on('usuarios.users')->nullOnDelete();
            $table->foreign('acta_generada_por')->references('id')->on('usuarios.users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.recepcion_lotes', function (Blueprint $table): void {
            $table->dropForeign(['recibido_por']);
            $table->dropForeign(['acta_generada_por']);
            $table->dropColumn([
                'recibido_por', 'verificado_en', 'suspendido_en',
                'acta_generada_por', 'acta_generada_en', 'firma_metadata',
            ]);
        });
    }
};
