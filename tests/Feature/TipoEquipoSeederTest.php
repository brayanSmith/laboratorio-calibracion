<?php

use App\Models\Tenant;
use App\Models\TipoEquipo;
use Database\Seeders\TipoEquipoSeeder;

test('el seeder crea los tipos de equipo por defecto en cada tenant sin duplicarlos', function () {
    $tenants = Tenant::factory()->count(2)->create();

    $this->seed(TipoEquipoSeeder::class);
    $this->seed(TipoEquipoSeeder::class);

    expect(TipoEquipo::whereIn('tenant_id', $tenants->modelKeys())->count())->toBe(60);

    expect(TipoEquipo::where('tenant_id', $tenants->first()->id)->where('nombre', 'Kit de Termocuplas')->firstOrFail())
        ->tipo_mantenimiento->toBe('B');
    expect(TipoEquipo::where('tenant_id', $tenants->first()->id)->where('nombre', 'Rugosímetro')->firstOrFail())
        ->tipo_mantenimiento->toBe('A');
});
