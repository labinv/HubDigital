<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

return new class extends Migration
{
    public $withinTransaction = true;

    public function up(): void
    {
        Schema::table('divulgacion.imagenes_taxonomicas', function (Blueprint $table): void {
            $table->char('sha256', 64)->nullable();
        });

        $almacenamiento = app(AlmacenamientoDepositos::class);
        DB::table('divulgacion.imagenes_taxonomicas')
            ->where('disco', 'r2')
            ->whereNotNull('ruta')
            ->orderBy('id')
            ->chunkById(100, function ($imagenes) use ($almacenamiento): void {
                foreach ($imagenes as $imagen) {
                    DB::table('divulgacion.imagenes_taxonomicas')
                        ->where('id', $imagen->id)
                        ->update(['sha256' => $almacenamiento->sha256((string) $imagen->ruta)]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('divulgacion.imagenes_taxonomicas', function (Blueprint $table): void {
            $table->dropColumn('sha256');
        });
    }
};
