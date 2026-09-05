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
            $table->timestamp('entrega_programada_para')->nullable()->after('codigo_qr');
            $table->timestamp('entrega_notificada_en')->nullable()->after('entrega_programada_para');
            $table->string('entrega_nota', 500)->nullable()->after('entrega_notificada_en');
            $table->index('entrega_programada_para');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->dropIndex(['entrega_programada_para']);
            $table->dropColumn(['entrega_programada_para', 'entrega_notificada_en', 'entrega_nota']);
        });
    }
};
