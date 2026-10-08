<?php

use App\Models\ProcedimientoCalibracion;
use App\Models\Tenant;
use Database\Seeders\ProcedimientoCalibracionSeeder;

test('el seeder crea los procedimientos de calibracion por defecto en cada tenant sin duplicarlos', function () {
    $tenants = Tenant::factory()->count(2)->create();

    $this->seed(ProcedimientoCalibracionSeeder::class);
    $this->seed(ProcedimientoCalibracionSeeder::class);

    expect(ProcedimientoCalibracion::whereIn('tenant_id', $tenants->modelKeys())->count())->toBe(22);

    $tenants->each(function (Tenant $tenant) {
        expect($tenant->procedimientosCalibracion()->count())->toBe(11);
    });
});

test('el seeder no sobrescribe los datos que el tenant ya edito', function () {
    $tenant = Tenant::factory()->create();
    ProcedimientoCalibracion::factory()->for($tenant)->create([
        'nombre' => 'PC-012 Procedimiento de Calibracion de Pies de Rey',
    ]);

    $this->seed(ProcedimientoCalibracionSeeder::class);

    expect($tenant->procedimientosCalibracion()
        ->where('nombre', 'PC-012 Procedimiento de Calibracion de Pies de Rey')
        ->count())->toBe(1);
});
