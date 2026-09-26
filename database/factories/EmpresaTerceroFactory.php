<?php

namespace Database\Factories;

use App\Models\EmpresaTercero;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmpresaTercero>
 */
class EmpresaTerceroFactory extends Factory
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
            'nit' => fake()->unique()->numerify('#########-#'),
            'direccion' => fake()->optional()->address(),
            'telefono' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
