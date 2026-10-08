<?php

namespace Database\Factories;

use App\Models\Novedad;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Novedad>
 */
class NovedadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->sentence(3),
            'categoria' => fake()->randomElement(Novedad::CATEGORIAS),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
