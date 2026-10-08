<?php

namespace Database\Seeders;

use App\Models\ProcedimientoCalibracion;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ProcedimientoCalibracionSeeder extends Seeder
{
    /**
     * Seed the default procedimientos de calibración for every tenant.
     *
     * Running it again does not duplicate procedimientos.
     */
    public function run(): void
    {
        $procedimientos = [
            'PC-003 Procedimiento de Calibración de Medidores Volumétricos Metálicos (Método Volumétrico)',
            'PC-004:2019 Procedimiento para la Calibración de Instrumentos de Medición de Presión Relativa con clase de Exactitud igual o mayor a 0,05 % F.S.',
            'PC-012 Procedimiento de Calibracion de Pies de Rey',
            'PC-013 Procedimiento de Calibración de Micrometros de Exteriores',
            'PC-017 Procedimiento para la Calibración de Termómetros Digitales',
            'PC-021 Procedimiento para la Calibración de Multímetros Digitales',
            'PC-024 Procedimiento para la Calibración de Instrumentos De Medición De Presión Absoluta (Barómetros)',
            'PC-025 Procedimiento para la Calibración de Pinzas Amperimétricas',
            'PC-026 Procedimiento para la Calibración de Higrómetros y Termómetros Ambientales',
            'PC-029 Procedimiento para la Calibración de Medidores de Espesores Por Ultrasonido',
            'PC-031:2021 Procedimiento para la Calibración de Herramientas Dinamométricas Manuales (Torquímetros)',
        ];

        Tenant::query()->each(function (Tenant $tenant) use ($procedimientos): void {
            foreach ($procedimientos as $nombre) {
                ProcedimientoCalibracion::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'nombre' => $nombre,
                ]);
            }
        });
    }
}
