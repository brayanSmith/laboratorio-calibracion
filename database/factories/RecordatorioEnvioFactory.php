<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Modules\Recordatorios\Models\RecordatorioEnvio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecordatorioEnvio>
 */
class RecordatorioEnvioFactory extends Factory
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
            'destinatario' => fake()->company(),
            'correo' => fake()->safeEmail(),
            'asunto' => 'Recordatorio de vencimiento de equipos',
            'equipos' => [],
            'tenant_id' => Tenant::factory(),
        ];
    }

    /**
     * Correo que ya salió.
     */
    public function enviado(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'ENVIADO', 'enviado_at' => now()]);
    }
}
