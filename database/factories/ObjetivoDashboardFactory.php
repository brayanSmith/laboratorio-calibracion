<?php

namespace Database\Factories;

use App\Enums\ConceptoObjetivo;
use App\Models\ObjetivoDashboard;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObjetivoDashboard>
 */
class ObjetivoDashboardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'anio' => (int) now()->format('Y'),
            'concepto' => fake()->randomElement(ConceptoObjetivo::cases()),
            'valor' => fake()->randomFloat(2, 1, 100),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
