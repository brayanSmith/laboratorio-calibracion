<?php

namespace Database\Seeders;

use App\Models\Laboratorio;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class LaboratorioSeeder extends Seeder
{
    /**
     * Seed the default laboratorios for every tenant.
     *
     * Running it again does not duplicate laboratorios.
     */
    public function run(): void
    {
        $laboratorios = [
            'Laboratorio de Calibraciones Ferreyros La Joya',
            'SEIMET',
            'LO JUSTO',
        ];

        Tenant::query()->each(function (Tenant $tenant) use ($laboratorios): void {
            foreach ($laboratorios as $nombre) {
                Laboratorio::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'nombre' => $nombre,
                ]);
            }
        });
    }
}
