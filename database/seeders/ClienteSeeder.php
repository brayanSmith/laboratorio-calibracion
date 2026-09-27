<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    /**
     * Seed 100 sample clientes for every tenant.
     *
     * Unlike the other seeders, these are randomly generated sample records, not fixed
     * reference data, so running it again adds 100 more clientes instead of skipping them.
     */
    public function run(): void
    {
        Tenant::query()->each(function (Tenant $tenant): void {
            Cliente::factory()->count(100)->for($tenant)->create();
        });
    }
}
