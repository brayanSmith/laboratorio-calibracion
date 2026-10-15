<?php

namespace Database\Factories;

use App\Models\AjustePlataforma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AjustePlataforma>
 */
class AjustePlataformaFactory extends Factory
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
            'logo' => null,
        ];
    }
}
