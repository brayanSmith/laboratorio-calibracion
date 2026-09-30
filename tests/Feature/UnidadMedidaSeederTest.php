<?php

use App\Models\Tenant;
use App\Models\UnidadMedida;
use Database\Seeders\UnidadMedidaSeeder;

test('el seeder crea las unidades de medida por defecto en cada tenant sin duplicarlas', function () {
    $tenants = Tenant::factory()->count(2)->create();

    $this->seed(UnidadMedidaSeeder::class);
    $this->seed(UnidadMedidaSeeder::class);

    expect(UnidadMedida::whereIn('tenant_id', $tenants->modelKeys())->count())->toBe(16);

    $tenants->each(function (Tenant $tenant) {
        $unidades = $tenant->unidadesMedida()->pluck('nombre', 'simbolo');

        expect($unidades)->toHaveCount(8)
            ->and($unidades['N-m'])->toBe('Newton-metro')
            ->and($unidades['µm'])->toBe('Micrómetro');
    });
});

test('el seeder no sobrescribe los datos que el tenant ya edito', function () {
    $tenant = Tenant::factory()->create();
    UnidadMedida::factory()->for($tenant)->create(['simbolo' => 'mm', 'nombre' => 'Milimetro personalizado']);

    $this->seed(UnidadMedidaSeeder::class);

    expect($tenant->unidadesMedida()->where('simbolo', 'mm')->count())->toBe(1)
        ->and($tenant->unidadesMedida()->where('simbolo', 'mm')->firstOrFail()->nombre)
        ->toBe('Milimetro personalizado');
});
