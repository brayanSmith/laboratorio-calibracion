<?php

use App\Models\Item;
use App\Models\Tenant;
use Database\Seeders\ItemSeeder;

test('el seeder crea los items por defecto en cada tenant sin duplicarlos', function () {
    $tenants = Tenant::factory()->count(2)->create();

    $this->seed(ItemSeeder::class);
    $this->seed(ItemSeeder::class);

    expect(Item::whereIn('tenant_id', $tenants->modelKeys())->count())->toBe(92);

    $tenants->each(function (Tenant $tenant) {
        expect($tenant->items()->count())->toBe(46)
            ->and($tenant->items()->where('codigo', '173369')->firstOrFail()->nombre)
            ->toBe("KIT RETENEDOR DE DADO 3/8'' P/PISTOLA IMPACTO INGERSOLL");
    });
});

test('el seeder actualiza el nombre de un item existente en lugar de duplicarlo', function () {
    $tenant = Tenant::factory()->create();
    Item::factory()->for($tenant)->create(['codigo' => '173355', 'nombre' => 'Nombre anterior']);

    $this->seed(ItemSeeder::class);

    expect($tenant->items()->where('codigo', '173355')->count())->toBe(1)
        ->and($tenant->items()->where('codigo', '173355')->firstOrFail()->nombre)
        ->toBe('SEGURO PARA MICROMETRO CLAMP HANDLE MITUTOYO');
});
