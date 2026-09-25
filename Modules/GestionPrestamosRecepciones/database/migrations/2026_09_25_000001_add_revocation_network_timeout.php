<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('recepciones.fuentes_revocacion_firma', 'tiempo_espera_segundos')) {
            Schema::table('recepciones.fuentes_revocacion_firma', function (Blueprint $table): void {
                $table->unsignedSmallInteger('tiempo_espera_segundos')->default(3);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('recepciones.fuentes_revocacion_firma', 'tiempo_espera_segundos')) {
            Schema::table('recepciones.fuentes_revocacion_firma', function (Blueprint $table): void {
                $table->dropColumn('tiempo_espera_segundos');
            });
        }
    }
};
