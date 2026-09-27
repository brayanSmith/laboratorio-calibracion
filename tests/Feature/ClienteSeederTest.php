<?php

use App\Models\Tenant;
use Database\Seeders\ClienteSeeder;

test('el seeder crea 100 clientes de prueba por tenant', function () {
    $tenants = Tenant::factory()->count(2)->create();

    $this->seed(ClienteSeeder::class);

    $tenants->each(function (Tenant $tenant) {
        expect($tenant->clientes()->count())->toBe(100);
    });
});

test('correr el seeder de nuevo agrega 100 clientes mas, no los reemplaza', function () {
    $tenant = Tenant::factory()->create();

    $this->seed(ClienteSeeder::class);
    $this->seed(ClienteSeeder::class);

    expect($tenant->clientes()->count())->toBe(200);
});
