<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TipoMagnitud;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoMagnitud>
 */
class TipoMagnitudFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word(),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
