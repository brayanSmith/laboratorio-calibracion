<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Bahia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bahia>
 */
class BahiaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->bothify('Bahía ##'),
            'area_id' => Area::factory(),
            'tenant_id' => fn (array $attributes) => Area::find($attributes['area_id'])->tenant_id,
        ];
    }
}
