<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TipoEquipo;
use Illuminate\Database\Seeder;

class TipoEquipoSeeder extends Seeder
{
    /**
     * Seed the default tipos de equipo for every tenant.
     *
     * Running it again does not duplicate tipos de equipo.
     */
    public function run(): void
    {
        $tiposEquipo = [
            'Alexometro Analógico' => 'A',
            'Calibrador Pie de Rey Digital' => 'A',
            'Kit de Termocuplas' => 'B',
            'Manómetro analógico' => 'A',
            'Manómetro Digital' => 'B',
            'Micrómetro Exterior Analógicos' => 'A',
            'Micrómetro Exterior Digital' => 'A',
            'Micrómetro Interior Digital' => 'A',
            'Pistola de Temperatura' => 'A',
            'Reloj Comparador Analógico' => 'A',
            'Reloj Comparador Digital' => 'A',
            'Reloj Comparador Palpador' => 'A',
            'Rugosímetro' => 'A',
            'Sensor de Presión' => 'A',
            'Tacometro' => 'A',
            'Taladro neumático' => 'B',
            'Torquimetro con reloj' => 'A',
            'Torquimetro de flexión con cabezal' => 'A',
            'Torquímetro Electrónico' => 'A',
            'Torquimetro Tipo Click' => 'A',
            'Torquimetro de flexión sin cabezal' => 'A',
            'HERRAMIENTA DE PRE-CARGA' => 'A',
            'LIJADORA NEUMÁTICA' => 'B',
            'PISTOLA ELÉCTRICA' => 'B',
            'Pistón Hidraúlico' => 'B',
            'TURBINA NEUMÁTICA' => 'B',
            'PISTOLA NEUMATICA' => 'B',
            'PISTOLA DE GIRO' => 'B',
            'Torque Hidráulico' => 'B',
            'Cadena de Izaje' => 'B',
        ];

        Tenant::query()->each(function (Tenant $tenant) use ($tiposEquipo): void {
            foreach ($tiposEquipo as $nombre => $tipoMantenimiento) {
                TipoEquipo::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'nombre' => $nombre],
                    ['tipo_mantenimiento' => $tipoMantenimiento],
                );
            }
        });
    }
}
