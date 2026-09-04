<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.catalogo_taxones_externos', function (Blueprint $table): void {
            $table->unsignedBigInteger('accepted_usage_key')->nullable()->after('specific_epithet');
            $table->unsignedBigInteger('parent_key')->nullable()->after('accepted_usage_key');
            $table->string('taxon_id', 255)->nullable()->after('parent_key');
            $table->string('accepted_name_usage', 500)->nullable()->after('taxon_id');
            $table->string('accepted_name_usage_id', 255)->nullable()->after('accepted_name_usage');
            $table->string('name_according_to', 255)->nullable()->after('accepted_name_usage_id');
            $table->string('name_according_to_id', 255)->nullable()->after('name_according_to');

            // Las claves superiores no son FK locales: GBIF puede devolver un padre
            // o nombre aceptado que aún no haya sido consultado por HubDigital.
            $table->index('accepted_usage_key', 'catalogo_taxon_accepted_key_idx');
            $table->index('parent_key', 'catalogo_taxon_parent_key_idx');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.catalogo_taxones_externos', function (Blueprint $table): void {
            $table->dropIndex('catalogo_taxon_accepted_key_idx');
            $table->dropIndex('catalogo_taxon_parent_key_idx');
            $table->dropColumn([
                'accepted_usage_key',
                'parent_key',
                'taxon_id',
                'accepted_name_usage',
                'accepted_name_usage_id',
                'name_according_to',
                'name_according_to_id',
            ]);
        });
    }
};
