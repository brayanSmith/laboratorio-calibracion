<?php

use App\Models\Laboratorio;
use App\Models\Tenant;
use Database\Seeders\LaboratorioSeeder;

test('el seeder crea los laboratorios por defecto en cada tenant sin duplicarlos', function () {
    $tenants = Tenant::factory()->count(2)->create();

    $this->seed(LaboratorioSeeder::class);
    $this->seed(LaboratorioSeeder::class);

    expect(Laboratorio::whereIn('tenant_id', $tenants->modelKeys())->count())->toBe(6);

    $tenants->each(function (Tenant $tenant) {
        expect($tenant->laboratorios()->orderBy('nombre')->pluck('nombre')->all())
            ->toBe(['LO JUSTO', 'Laboratorio de Calibraciones Ferreyros La Joya', 'SEIMET']);
    });
});

test('el seeder no sobrescribe los datos que el tenant ya edito', function () {
    $tenant = Tenant::factory()->create();
    Laboratorio::factory()->for($tenant)->create(['nombre' => 'SEIMET', 'direccion' => 'Arequipa']);

    $this->seed(LaboratorioSeeder::class);

    expect($tenant->laboratorios()->where('nombre', 'SEIMET')->count())->toBe(1)
        ->and($tenant->laboratorios()->where('nombre', 'SEIMET')->firstOrFail()->direccion)->toBe('Arequipa');
});
