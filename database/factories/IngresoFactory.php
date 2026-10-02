<?php

namespace Database\Factories;

use App\Models\Bahia;
use App\Models\Cliente;
use App\Models\Ingreso;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingreso>
 */
class IngresoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $desde = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'bahia_id' => Bahia::factory(),
            'desde' => $desde,
            'hasta' => fake()->dateTimeBetween($desde, '+1 week'),
            'tecnico_recibe_id' => fn (array $attributes) => User::factory()->create([
                'tenant_id' => Bahia::find($attributes['bahia_id'])->tenant_id,
            ])->id,
            'cliente_entrega_id' => fn (array $attributes) => Cliente::factory()->create([
                'tenant_id' => Bahia::find($attributes['bahia_id'])->tenant_id,
            ])->id,
            'firma_cliente_entrega' => null,
            'estado_ingreso' => 'RECIBIDO',
            'novedad' => null,
            'tenant_id' => fn (array $attributes) => Bahia::find($attributes['bahia_id'])->tenant_id,
        ];
    }

    /**
     * Indicate that the ingreso was cancelado and has a novedad.
     */
    public function fallido(): static
    {
        return $this->state(fn () => [
            'estado_ingreso' => 'CANCELADO',
            'novedad' => fake()->sentence(),
        ]);
    }
}
