<?php

namespace Database\Factories;

use App\Models\ObjetivoUser;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObjetivoUser>
 */
class ObjetivoUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'anio' => (int) now()->format('Y'),
            'mes' => null,
            'tipo' => fake()->randomElement(ObjetivoUser::TIPOS),
            'valor' => fake()->randomFloat(2, 1, 100),
            'tenant_id' => Tenant::factory(),
        ];
    }

    /**
     * Objetivo de un mes del año en lugar de todo el año.
     */
    public function mensual(int $mes = 1): static
    {
        return $this->state(fn () => ['mes' => $mes]);
    }
}
