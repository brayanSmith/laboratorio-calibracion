<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TipoMagnitud;
use Illuminate\Database\Seeder;

class TipoMagnitudSeeder extends Seeder
{
    /**
     * Seed the default tipos de magnitud for every tenant.
     *
     * Running it again does not duplicate tipos de magnitud.
     */
    public function run(): void
    {
        $tiposMagnitud = [
            'Torque',
            'Longitud',
            'Temperatura',
            'Caudal',
            'Presión',
            'Velocidad',
            'Grados',
            'Dureza',
            'Micras/Volumen',
            'Resistencia Eléctrica',
            'Frecuencia',
            'Magnetismo',
            'Volumen',
            'Voltaje',
            'Gases',
            'Iluminancia',
            'Masa',
            'Viscosidad',
            'Tiempo',
            'Conductividad Eléctrica',
            'Corriente',
            'Otros',
        ];

        Tenant::query()->each(function (Tenant $tenant) use ($tiposMagnitud): void {
            foreach ($tiposMagnitud as $nombre) {
                TipoMagnitud::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'nombre' => $nombre,
                ]);
            }
        });
    }
}
