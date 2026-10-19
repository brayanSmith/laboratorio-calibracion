<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Modules\AlcancesMedicion\Models\DetalleMedicionAlcance;
use App\Modules\AlcancesMedicion\Models\MedicionAlcance;
use App\Modules\Catalogos\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DetalleMedicionAlcance>
 */
class DetalleMedicionAlcanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'medicion_alcance_id' => fn (array $attributes) => MedicionAlcance::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'unidad_medida_id' => fn (array $attributes) => UnidadMedida::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'valor_referencia' => fake()->randomFloat(2, 1, 1000),
            'emp' => fake()->randomFloat(2, 0, 10),
            'incertidumbre' => fake()->randomFloat(2, 0, 5),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
