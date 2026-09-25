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
            $table->string('canton_origen', 100)->nullable()->after('provincia_origen');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->dropColumn('canton_origen');
        });
    }
};
