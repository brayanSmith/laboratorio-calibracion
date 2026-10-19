<?php

use App\Models\Tenant;
use App\Modules\Catalogos\Models\Area;
use Database\Seeders\AreaSeeder;

test('el seeder crea las areas por defecto en cada tenant sin duplicarlas', function () {
    $tenants = Tenant::factory()->count(2)->create();

    $this->seed(AreaSeeder::class);
    $this->seed(AreaSeeder::class);

    expect(Area::whereIn('tenant_id', $tenants->modelKeys())->count())->toBe(10);

    $tenants->each(function (Tenant $tenant) {
        expect($tenant->areas()->pluck('nombre')->sort()->values()->all())
            ->toBe(['CRC', 'TCE', 'TMAQ', 'TMM', 'TSOL']);
    });

    expect(Area::where('nombre', 'TSOL')->firstOrFail())
        ->descripcion->toBe('Taller de Soldadura')
        ->direccion->toBe('Lote 1A A.H. Progreso 48 La Granja de mi Abuela Arequipa');
});

test('el factory de areas crea un area valida del tenant', function () {
    $tenant = Tenant::factory()->create();

    $area = Area::factory()->for($tenant)->create();

    expect($area->tenant_id)->toBe($tenant->id)->and($area->nombre)->not->toBeEmpty();
});

test('la migracion intercambia nombre y descripcion de las areas por defecto una sola vez', function () {
    $tenant = Tenant::factory()->create();
    $codigo = Area::factory()->for($tenant)->create(['nombre' => 'Taller de Soldadura', 'descripcion' => 'TSOL']);
    $propia = Area::factory()->for($tenant)->create(['nombre' => 'Mi área', 'descripcion' => 'Área propia']);

    $migracion = require database_path('migrations/2026_10_17_015032_swap_nombre_and_descripcion_in_default_areas.php');
    $migracion->up();
    $migracion->up();

    expect($codigo->fresh())->nombre->toBe('TSOL')->descripcion->toBe('Taller de Soldadura')
        ->and($propia->fresh())->nombre->toBe('Mi área')->descripcion->toBe('Área propia');
});
