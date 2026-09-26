<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    private const DIRECCION = 'Lote 1A A.H. Progreso 48 La Granja de mi Abuela Arequipa';

    /**
     * Seed the default areas for every tenant.
     *
     * The area code is stored in the descripcion column. Running it again does not duplicate areas.
     */
    public function run(): void
    {
        $areas = [
            'CRC' => 'Centro de Reparación de Componentes',
            'TMM' => 'Taller de Metalizado y Mecanizado',
            'TSOL' => 'Taller de Soldadura',
            'TMAQ' => 'Taller de Máquinas',
            'TCE' => 'Taller de Componentes Eléctricos',
        ];

        Tenant::query()->each(function (Tenant $tenant) use ($areas): void {
            foreach ($areas as $codigo => $nombre) {
                Area::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'nombre' => $nombre],
                    ['descripcion' => $codigo, 'direccion' => self::DIRECCION],
                );
            }
        });
    }
}
