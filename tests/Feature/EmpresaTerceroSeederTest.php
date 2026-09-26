<?php

use App\Models\EmpresaTercero;
use App\Models\Tenant;
use Database\Seeders\EmpresaTerceroSeeder;

test('el seeder crea las empresas terceras por defecto en cada tenant sin duplicarlas', function () {
    $tenants = Tenant::factory()->count(2)->create();

    $this->seed(EmpresaTerceroSeeder::class);
    $this->seed(EmpresaTerceroSeeder::class);

    expect(EmpresaTercero::whereIn('tenant_id', $tenants->modelKeys())->count())->toBe(6);

    $tenants->each(function (Tenant $tenant) {
        expect($tenant->empresaTerceros()->orderBy('nombre')->pluck('nombre')->all())
            ->toBe(['BHU', 'LO JUSTO', 'SEIMET'])
            ->and($tenant->empresaTerceros()->where('nombre', 'SEIMET')->firstOrFail())
            ->direccion->toBe('Arequipa')
            ->telefono->toBe('11122223333');
    });
});

test('el seeder no sobrescribe los datos que el tenant ya edito', function () {
    $tenant = Tenant::factory()->create();
    EmpresaTercero::factory()->for($tenant)->create(['nombre' => 'SEIMET', 'nit' => '20601234567', 'telefono' => '999888777']);

    $this->seed(EmpresaTerceroSeeder::class);

    expect($tenant->empresaTerceros()->where('nombre', 'SEIMET')->count())->toBe(1)
        ->and($tenant->empresaTerceros()->where('nombre', 'SEIMET')->firstOrFail())
        ->nit->toBe('20601234567')
        ->telefono->toBe('999888777');
});
