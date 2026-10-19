<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('ITM-####'),
            'nombre' => fake()->words(2, true),
            'descripcion' => fake()->optional()->sentence(),
            'ubicacion' => fake()->optional()->bothify('Estante ?-##'),
            'precio_unitario' => fake()->optional()->randomFloat(2, 1, 500),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
