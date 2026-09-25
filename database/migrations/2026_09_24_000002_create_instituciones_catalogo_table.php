<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios.instituciones_catalogo', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('codigo_ces', 12)->nullable()->unique();
            $table->string('nombre', 160)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios.instituciones_catalogo');
    }
};
