<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TipoEquipo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoEquipo>
 */
class TipoEquipoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'tipo_mantenimiento' => fake()->randomElement(['A', 'B']),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
