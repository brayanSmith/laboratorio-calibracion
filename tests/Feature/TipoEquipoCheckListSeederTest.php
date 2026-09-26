<?php

use App\Models\Tenant;
use App\Models\TipoEquipo;
use App\Models\TipoEquipoCheckList;
use Database\Seeders\TipoEquipoCheckListSeeder;

test('el seeder crea el checklist de cada tipo de equipo en cada tenant sin duplicarlo', function () {
    $tenants = Tenant::factory()->count(2)->create();

    $this->seed(TipoEquipoCheckListSeeder::class);
    $this->seed(TipoEquipoCheckListSeeder::class);

    expect(TipoEquipoCheckList::whereIn('tenant_id', $tenants->modelKeys())->count())->toBe(480);

    $tenants->each(function (Tenant $tenant) {
        $tiposEquipo = TipoEquipo::query()->where('tenant_id', $tenant->id)->withCount('tipoEquipoCheckList')->get();

        expect($tiposEquipo)->toHaveCount(30)
            ->and($tiposEquipo->where('tipo_equipo_check_list_count', 0))->toBeEmpty();
    });
});

test('el checklist conserva el orden y pertenece al tipo de equipo y tenant correctos', function () {
    $tenant = Tenant::factory()->create();

    $this->seed(TipoEquipoCheckListSeeder::class);

    $checklist = TipoEquipo::query()
        ->where('tenant_id', $tenant->id)
        ->where('nombre', 'Kit de Termocuplas')
        ->firstOrFail()
        ->tipoEquipoCheckList()
        ->orderBy('id')
        ->get();

    expect($checklist->pluck('nombre')->all())->toBe([
        '1. Se realiza la inspección visual de las termocuplas.',
        '2. Se realiza la limpieza e inspección de los accesorios.',
        '3. Se verifica los contactos eléctricos.',
        '4. Se envía al LDC/Proveedor para su calibración.',
    ])->and($checklist->pluck('tenant_id')->unique()->all())->toBe([$tenant->id]);
});
