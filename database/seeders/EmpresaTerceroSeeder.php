<?php

namespace Database\Seeders;

use App\Models\EmpresaTercero;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class EmpresaTerceroSeeder extends Seeder
{
    /**
     * Seed the default empresas terceras for every tenant.
     *
     * The NIT values are placeholders to be replaced with the real ones.
     * Running it again does not duplicate empresas terceras nor overwrite the data already edited.
     */
    public function run(): void
    {
        $empresasTerceras = [
            ['nombre' => 'SEIMET', 'nit' => '10000000001', 'direccion' => 'Arequipa', 'telefono' => '11122223333'],
            ['nombre' => 'LO JUSTO', 'nit' => '10000000002', 'direccion' => 'Arequipa', 'telefono' => '11122223333'],
            ['nombre' => 'BHU', 'nit' => '10000000003', 'direccion' => 'Arequipa', 'telefono' => '11122223333'],
        ];

        Tenant::query()->each(function (Tenant $tenant) use ($empresasTerceras): void {
            foreach ($empresasTerceras as $empresaTercero) {
                EmpresaTercero::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'nombre' => $empresaTercero['nombre']],
                    [
                        'nit' => $empresaTercero['nit'],
                        'direccion' => $empresaTercero['direccion'],
                        'telefono' => $empresaTercero['telefono'],
                    ],
                );
            }
        });
    }
}
