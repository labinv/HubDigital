<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios.configuracion_expediente', function (Blueprint $table): void {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('prefijo', 40);
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios.configuracion_expediente');
    }
};
