<?php

namespace Database\Factories;

use App\Models\MedicionAlcance;
use App\Models\Tenant;
use App\Models\TipoEquipo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicionAlcance>
 */
class MedicionAlcanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_equipo_id' => fn (array $attributes) => TipoEquipo::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'alcance_indicacion' => fake()->unique()->numerify('0 - ### bar'),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
