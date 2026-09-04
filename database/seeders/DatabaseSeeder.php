<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (filter_var(env('SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOL)) {
            $this->call(DepositosDemoSeeder::class);
        }
    }
}
