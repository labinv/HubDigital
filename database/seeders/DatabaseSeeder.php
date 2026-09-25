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
        $this->call(InstitucionesCatalogoSeeder::class);
        if (filter_var(env('SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOL)) {
            $this->call(DepositosDemoSeeder::class);
        }

        if (filter_var(env('SEED_BOOTSTRAP_DEPOSITANTE', false), FILTER_VALIDATE_BOOL)) {
            $this->call(DepositanteBootstrapSeeder::class);
        }
    }
}
