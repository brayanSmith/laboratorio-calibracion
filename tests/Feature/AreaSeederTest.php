<?php

use App\Models\Area;
use App\Models\Tenant;
use Database\Seeders\AreaSeeder;

test('el seeder crea las areas por defecto en cada tenant sin duplicarlas', function () {
    $tenants = Tenant::factory()->count(2)->create();

    $this->seed(AreaSeeder::class);
    $this->seed(AreaSeeder::class);

    expect(Area::whereIn('tenant_id', $tenants->modelKeys())->count())->toBe(10);

    $tenants->each(function (Tenant $tenant) {
        expect($tenant->areas()->pluck('descripcion')->sort()->values()->all())
            ->toBe(['CRC', 'TCE', 'TMAQ', 'TMM', 'TSOL']);
    });

    expect(Area::where('descripcion', 'TSOL')->firstOrFail())
        ->nombre->toBe('Taller de Soldadura')
        ->direccion->toBe('Lote 1A A.H. Progreso 48 La Granja de mi Abuela Arequipa');
});

test('el factory de areas crea un area valida del tenant', function () {
    $tenant = Tenant::factory()->create();

    $area = Area::factory()->for($tenant)->create();

    expect($area->tenant_id)->toBe($tenant->id)->and($area->nombre)->not->toBeEmpty();
});
