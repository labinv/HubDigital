<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('recepciones.solicitudes_deposito', 'validacion_archivos')) {
            Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
                $table->jsonb('validacion_archivos')->default('{}');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('recepciones.solicitudes_deposito', 'validacion_archivos')) {
            Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
                $table->dropColumn('validacion_archivos');
            });
        }
    }
};
