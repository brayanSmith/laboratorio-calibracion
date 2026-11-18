<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Modules\Ingresos\Models\FotoRecepcion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pásale el equipo_programacion_id (y el tenant_id de esa programación).
 *
 * @extends Factory<FotoRecepcion>
 */
class FotoRecepcionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ruta' => 'fotos-recepcion/'.fake()->uuid().'.jpg',
            'tenant_id' => Tenant::factory(),
        ];
    }
}
