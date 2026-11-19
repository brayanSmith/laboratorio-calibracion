<?php

namespace Database\Factories;

use App\Modules\Ingresos\Models\Ingreso;
use App\Modules\Ingresos\Models\IngresoEnvio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngresoEnvio>
 */
class IngresoEnvioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ingreso_id' => Ingreso::factory(),
            'destinatario' => fake()->company(),
            'correo' => fake()->safeEmail(),
            'tenant_id' => fn (array $attributes) => Ingreso::query()->find($attributes['ingreso_id'])->tenant_id,
        ];
    }

    /**
     * Correo que ya salió.
     */
    public function enviado(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'ENVIADO', 'enviado_at' => now()]);
    }

    /**
     * Correo que no se pudo enviar.
     */
    public function fallido(string $error = 'Connection refused'): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'FALLIDO', 'error' => $error]);
    }
}
