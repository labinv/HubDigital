<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS recepciones');

        if (! Schema::hasTable('recepciones.documentos_regulatorios')) {
            Schema::create('recepciones.documentos_regulatorios', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('solicitud_id');
                $table->string('tipo_esperado', 60);
                $table->string('tipo_detectado', 60);
                $table->string('nombre_original', 255)->nullable();
                $table->text('ruta');
                $table->char('sha256', 64);
                $table->string('motor_ocr', 80)->nullable();
                $table->decimal('confianza', 5, 4)->default(0);
                $table->string('numero_documento', 255)->nullable();
                $table->string('numero_autorizacion_relacionada', 255)->nullable();
                $table->string('titular', 255)->nullable();
                $table->string('organizacion', 255)->nullable();
                $table->string('ruc', 20)->nullable();
                $table->text('proyecto')->nullable();
                $table->date('emitido_en')->nullable();
                $table->date('valido_desde')->nullable();
                $table->date('valido_hasta')->nullable();
                $table->string('estado_validacion', 30);
                $table->jsonb('contenido_extraido')->default('{}');
                $table->jsonb('indicadores')->default('{}');
                $table->jsonb('errores')->default('[]');
                $table->jsonb('advertencias')->default('[]');
                $table->timestamps();

                $table->unique(['solicitud_id', 'tipo_esperado'], 'doc_reg_solicitud_tipo_unique');
                $table->index(['solicitud_id', 'estado_validacion'], 'doc_reg_solicitud_estado_idx');
                $table->index('numero_documento', 'doc_reg_numero_idx');
                $table->foreign('solicitud_id')
                    ->references('id')
                    ->on('recepciones.solicitudes_deposito')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('recepciones.catalogo_taxones_externos')) {
            Schema::create('recepciones.catalogo_taxones_externos', function (Blueprint $table): void {
                $table->unsignedBigInteger('gbif_key')->primary();
                $table->string('scientific_name', 255);
                $table->string('canonical_name', 255);
                $table->string('rank', 50);
                $table->string('taxonomic_status', 50);
                $table->string('kingdom', 120)->nullable();
                $table->string('phylum', 120)->nullable();
                $table->string('class', 120)->nullable();
                $table->string('order', 120)->nullable();
                $table->string('family', 120)->nullable();
                $table->string('genus', 120)->nullable();
                $table->string('specific_epithet', 120)->nullable();
                $table->jsonb('respuesta_fuente')->default('{}');
                $table->timestampTz('sincronizado_en');
                $table->timestamps();

                $table->index('canonical_name', 'catalogo_taxon_canonical_idx');
                $table->index(['rank', 'taxonomic_status'], 'catalogo_taxon_rank_status_idx');
            });
        }

        if (! Schema::hasTable('recepciones.catalogo_grupos_invertebrados')) {
            Schema::create('recepciones.catalogo_grupos_invertebrados', function (Blueprint $table): void {
                $table->string('codigo', 50)->primary();
                $table->string('nombre', 160);
                $table->string('rango_referencia', 50);
                $table->unsignedSmallInteger('orden_visual');
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });

            $ahora = now();
            DB::table('recepciones.catalogo_grupos_invertebrados')->insert([
                ['codigo' => 'PORIFERA', 'nombre' => 'Poríferos', 'rango_referencia' => 'phylum', 'orden_visual' => 10, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['codigo' => 'CNIDARIA', 'nombre' => 'Cnidarios', 'rango_referencia' => 'phylum', 'orden_visual' => 20, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['codigo' => 'PLATYHELMINTHES', 'nombre' => 'Platelmintos', 'rango_referencia' => 'phylum', 'orden_visual' => 30, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['codigo' => 'NEMATODA', 'nombre' => 'Nematodos', 'rango_referencia' => 'phylum', 'orden_visual' => 40, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['codigo' => 'MOLLUSCA', 'nombre' => 'Moluscos', 'rango_referencia' => 'phylum', 'orden_visual' => 50, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['codigo' => 'ANNELIDA', 'nombre' => 'Anélidos', 'rango_referencia' => 'phylum', 'orden_visual' => 60, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['codigo' => 'INSECTA', 'nombre' => 'Insectos', 'rango_referencia' => 'class', 'orden_visual' => 70, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['codigo' => 'ARACHNIDA', 'nombre' => 'Arácnidos', 'rango_referencia' => 'class', 'orden_visual' => 80, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['codigo' => 'CRUSTACEA', 'nombre' => 'Crustáceos', 'rango_referencia' => 'subphylum', 'orden_visual' => 90, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['codigo' => 'MYRIAPODA', 'nombre' => 'Miriápodos', 'rango_referencia' => 'subphylum', 'orden_visual' => 100, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['codigo' => 'ONYCHOPHORA', 'nombre' => 'Onicóforos', 'rango_referencia' => 'phylum', 'orden_visual' => 110, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['codigo' => 'ECHINODERMATA', 'nombre' => 'Equinodermos', 'rango_referencia' => 'phylum', 'orden_visual' => 120, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['codigo' => 'OTRO_INVERTEBRADO', 'nombre' => 'Otro invertebrado', 'rango_referencia' => 'group', 'orden_visual' => 999, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ]);
        }

        if (! Schema::hasTable('recepciones.catalogo_paises')) {
            Schema::create('recepciones.catalogo_paises', function (Blueprint $table): void {
                $table->char('codigo_iso2', 2)->primary();
                $table->string('nombre_es', 120);
                $table->string('continente', 80);
                $table->unsignedSmallInteger('orden_visual');
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });

            $ahora = now();
            $paises = [
                ['EC', 'Ecuador', 'América del Sur', 1], ['CO', 'Colombia', 'América del Sur', 10],
                ['PE', 'Perú', 'América del Sur', 20], ['BO', 'Bolivia', 'América del Sur', 30],
                ['BR', 'Brasil', 'América del Sur', 40], ['VE', 'Venezuela', 'América del Sur', 50],
                ['GY', 'Guyana', 'América del Sur', 60], ['SR', 'Surinam', 'América del Sur', 70],
                ['GF', 'Guayana Francesa', 'América del Sur', 80], ['CL', 'Chile', 'América del Sur', 90],
                ['AR', 'Argentina', 'América del Sur', 100], ['PY', 'Paraguay', 'América del Sur', 110],
                ['UY', 'Uruguay', 'América del Sur', 120], ['PA', 'Panamá', 'América Central', 130],
                ['CR', 'Costa Rica', 'América Central', 140], ['MX', 'México', 'América del Norte', 150],
                ['US', 'Estados Unidos', 'América del Norte', 160], ['ES', 'España', 'Europa', 170],
            ];
            DB::table('recepciones.catalogo_paises')->insert(array_map(
                static fn (array $pais): array => [
                    'codigo_iso2' => $pais[0], 'nombre_es' => $pais[1], 'continente' => $pais[2],
                    'orden_visual' => $pais[3], 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
                ],
                $paises,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recepciones.catalogo_paises');
        Schema::dropIfExists('recepciones.catalogo_grupos_invertebrados');
        Schema::dropIfExists('recepciones.catalogo_taxones_externos');
        Schema::dropIfExists('recepciones.documentos_regulatorios');
    }
};
