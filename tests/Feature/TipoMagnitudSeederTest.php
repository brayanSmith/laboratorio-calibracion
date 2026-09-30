<?php

use App\Models\Tenant;
use App\Models\TipoMagnitud;
use Database\Seeders\TipoMagnitudSeeder;

test('el seeder crea los tipos de magnitud por defecto en cada tenant sin duplicarlos', function () {
    $tenants = Tenant::factory()->count(2)->create();

    $this->seed(TipoMagnitudSeeder::class);
    $this->seed(TipoMagnitudSeeder::class);

    expect(TipoMagnitud::whereIn('tenant_id', $tenants->modelKeys())->count())->toBe(44);

    $tenants->each(function (Tenant $tenant) {
        $nombres = $tenant->tiposMagnitud()->pluck('nombre');

        expect($nombres)->toHaveCount(22)
            ->and($nombres->contains('Torque'))->toBeTrue()
            ->and($nombres->contains('Otros'))->toBeTrue();
    });
});

test('el seeder no duplica el nombre repetido en la lista original', function () {
    $tenant = Tenant::factory()->create();

    $this->seed(TipoMagnitudSeeder::class);

    expect($tenant->tiposMagnitud()->where('nombre', 'Longitud')->count())->toBe(1);
});
