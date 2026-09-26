<?php

namespace Database\Factories;

use App\Models\Fabricante;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fabricante>
 */
class FabricanteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->company(),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
