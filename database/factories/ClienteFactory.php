<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->optional()->phoneNumber(),
            'direccion' => fake()->optional()->address(),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
