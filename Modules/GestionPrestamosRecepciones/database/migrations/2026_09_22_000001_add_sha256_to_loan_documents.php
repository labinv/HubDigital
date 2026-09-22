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
        Schema::table('prestamos.actas_prestamo', function (Blueprint $table): void {
            $table->char('pdf_firmado_sha256', 64)->nullable();
            $table->char('documento_identidad_sha256', 64)->nullable();
            $table->char('documento_exportacion_sha256', 64)->nullable();
            $table->char('pdf_firmado_curador_sha256', 64)->nullable();
        });

        $almacenamiento = app(AlmacenamientoDepositos::class);
        DB::table('prestamos.actas_prestamo')
            ->where(function ($query): void {
                $query->whereNotNull('pdf_firmado_ruta')
                    ->orWhereNotNull('documento_identidad_ruta')
                    ->orWhereNotNull('documento_exportacion_ruta')
                    ->orWhereNotNull('pdf_firmado_curador_ruta');
            })
            ->orderBy('id')
            ->chunkById(100, function ($actas) use ($almacenamiento): void {
                foreach ($actas as $acta) {
                    $actualizaciones = [];
                    foreach ([
                        'pdf_firmado_ruta' => 'pdf_firmado_sha256',
                        'documento_identidad_ruta' => 'documento_identidad_sha256',
                        'documento_exportacion_ruta' => 'documento_exportacion_sha256',
                        'pdf_firmado_curador_ruta' => 'pdf_firmado_curador_sha256',
                    ] as $campoRuta => $campoSha) {
                        $ruta = $acta->{$campoRuta};
                        if (is_string($ruta) && $ruta !== '') {
                            $actualizaciones[$campoSha] = $almacenamiento->sha256($ruta);
                        }
                    }
                    if ($actualizaciones !== []) {
                        DB::table('prestamos.actas_prestamo')
                            ->where('id', $acta->id)
                            ->update($actualizaciones);
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('prestamos.actas_prestamo', function (Blueprint $table): void {
            $table->dropColumn([
                'pdf_firmado_sha256',
                'documento_identidad_sha256',
                'documento_exportacion_sha256',
                'pdf_firmado_curador_sha256',
            ]);
        });
    }
};
