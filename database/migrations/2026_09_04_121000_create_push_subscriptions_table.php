<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable(config('webpush.table_name', 'push_subscriptions'))) {
            return;
        }

        Schema::connection(config('webpush.database_connection'))->create(
            config('webpush.table_name', 'push_subscriptions'),
            function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('subscribable_type');
                $table->uuid('subscribable_id');
                $table->text('endpoint')->unique();
                $table->string('public_key')->nullable();
                $table->string('auth_token')->nullable();
                $table->string('content_encoding')->nullable();
                $table->timestamps();

                $table->index(
                    ['subscribable_type', 'subscribable_id'],
                    'push_subscriptions_subscribable_morph_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::connection(config('webpush.database_connection'))
            ->dropIfExists(config('webpush.table_name', 'push_subscriptions'));
    }
};
