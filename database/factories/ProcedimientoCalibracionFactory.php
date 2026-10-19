<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Modules\Calibraciones\Models\ProcedimientoCalibracion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcedimientoCalibracion>
 */
class ProcedimientoCalibracionFactory extends Factory
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
            'tenant_id' => Tenant::factory(),
        ];
    }
}
