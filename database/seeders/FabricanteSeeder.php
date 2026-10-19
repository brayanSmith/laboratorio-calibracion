<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Modules\Catalogos\Models\Fabricante;
use Illuminate\Database\Seeder;

class FabricanteSeeder extends Seeder
{
    /**
     * Seed the default fabricantes for every tenant.
     *
     * Running it again does not duplicate fabricantes.
     */
    public function run(): void
    {
        $fabricantes = array_unique([
            'Snap-On', 'Caterpillar', 'Mitutoyo', 'Sunnen', 'Technology Design', 'Starrett', 'Cdi',
            'Thermolaser', 'Sola', 'Webtec', 'Winters', 'Bosch', 'Hytorc', 'Evotorque',
            'Cdi Torque Products', 'Mahr Federal', 'Cat', 'Fluke', 'Pcb Piezotronics', 'Proto',
            'Sweeney', 'Power Test', 'Nuova Fima', 'Monarch', 'Wasco', 'Setra', 'Danfoss', 'Ast',
            'Omega', 'Precision Instruments', 'Cm', 'Wika', 'Atlas Copco', 'Elephant', 'Tecsis', 'Sn',
            'Power Net', 'Case', 'Karl Deutsch', 'Skf', 'Sensing Systems Corporation', 'Fabricacion',
            'Vafc', 'Nuovafima', 'Phase Ii', 'Bahco', 'Keli Sensing Technology', 'Pamas', 'Hydac',
            'Extech', 'Elcometer', 'Starret', 'Sonel', 'Igaging', 'Hydracheck', 'Roeckle',
            'Crystal Engineering', 'Wintact', 'Ametek', 'Spectroline', 'Uni-T', 'Rad', 'Enerpac',
            'S.H.M', 'Parker', 'Brand', 'Perkin Elmer', 'Msa', 'Spx', 'Ith', 'Magnaflux',
            'Spectro Scientific', 'Htz', 'Reles', 'Kessler', 'Miller & Weber', 'Kern', 'Corning',
            'Gilson', 'Werka', 'Flow Technology', 'Isl', 'Isolab', 'Thermoscientific', 'Boeco',
            'Renishaw', 'Accusplit', 'Dyer', 'Miller', 'Api Radian', 'Yamato', 'Atago', 'Hanna',
            'Sylvac', 'Haimer', 'Iscar Member Imc Group', 'Rexroth', 'Bosh', 'Norbar', 'Unit',
            'Steelmax', 'Hedland', 'Peaktech', 'Crystal Pressure', 'Tekton', 'Proceq', 'Marh',
            'Hexagon', 'B-Rad', 'Lsm', 'Megger', 'Johnson', 'Mestek', 'Trimos', 'Bowers',
        ]);

        Tenant::query()->each(function (Tenant $tenant) use ($fabricantes): void {
            foreach ($fabricantes as $nombre) {
                Fabricante::query()->firstOrCreate(['tenant_id' => $tenant->id, 'nombre' => $nombre]);
            }
        });
    }
}
