<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;
use Illuminate\Database\Seeder;

class BahiaSeeder extends Seeder
{
    /**
     * Seed the default bahias for every tenant, under the area whose code (stored in the
     * nombre column, see AreaSeeder) matches. Run it after AreaSeeder.
     *
     * Running it again does not duplicate bahias.
     */
    public function run(): void
    {
        $bahias = [
            'CRC' => [
                'A-01', 'A-02', 'DINA1', 'BH1', 'CENTRO MECANIZADO CNC',
                'ROTTLER F80', 'FRESADORA', 'ROTTLER F105A',
            ],
        ];

        Tenant::query()->each(function (Tenant $tenant) use ($bahias): void {
            foreach ($bahias as $codigoArea => $nombres) {
                $area = Area::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('nombre', $codigoArea)
                    ->first();

                if (! $area) {
                    continue;
                }

                foreach ($nombres as $nombre) {
                    Bahia::query()->firstOrCreate([
                        'tenant_id' => $tenant->id,
                        'area_id' => $area->id,
                        'nombre' => $nombre,
                    ]);
                }
            }
        });
    }
}
