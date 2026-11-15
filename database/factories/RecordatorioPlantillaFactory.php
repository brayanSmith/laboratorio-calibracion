<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Modules\Recordatorios\Models\RecordatorioPlantilla;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecordatorioPlantilla>
 */
class RecordatorioPlantillaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => 'cliente',
            'nombre' => fake()->unique()->words(2, true),
            'asunto' => 'Recordatorio - {empresa}',
            'mensaje' => '<p>Hola, {nombre}:</p><p>{tabla}</p>',
            'tenant_id' => Tenant::factory(),
        ];
    }

    /**
     * Plantilla del resumen por bahía.
     */
    public function bahia(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => 'bahia']);
    }
}
