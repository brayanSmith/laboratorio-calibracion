<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class UnidadMedidaSeeder extends Seeder
{
    /**
     * Seed the default unidades de medida for every tenant.
     *
     * Running it again does not duplicate unidades de medida.
     */
    public function run(): void
    {
        $unidadesMedida = [
            'lb-ft' => 'Libra-pie',
            'in-lb' => 'Pulgada-libra',
            'N-m' => 'Newton-metro',
            'Psi' => 'Libra por pulgada cuadrada',
            'mm' => 'Milímetro',
            'µm' => 'Micrómetro',
            'pulg' => 'Pulgada',
            'mil' => 'Milésima de pulgada',
        ];

        Tenant::query()->each(function (Tenant $tenant) use ($unidadesMedida): void {
            foreach ($unidadesMedida as $simbolo => $nombre) {
                UnidadMedida::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'simbolo' => $simbolo,
                ], [
                    'nombre' => $nombre,
                ]);
            }
        });
    }
}
