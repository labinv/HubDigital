<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('recepciones.fuentes_revocacion_firma')) {
            Schema::create('recepciones.fuentes_revocacion_firma', function (Blueprint $table): void {
                $table->string('codigo', 50)->primary();
                $table->string('entidad', 180);
                $table->string('patron_emisor', 180);
                $table->text('crl_url')->nullable();
                $table->text('ocsp_url')->nullable();
                $table->boolean('revocacion_obligatoria')->default(false);
                $table->boolean('activo')->default(true);
                $table->unsignedSmallInteger('horas_actualizacion')->default(8);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recepciones.fuentes_revocacion_firma');
    }
};
