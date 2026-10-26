<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Ingresos\Models\Ingreso;
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
            'tenant_id' => Tenant::factory(),
            'desde' => $desde,
            'hasta' => fake()->dateTimeBetween($desde, '+1 week'),
            'tecnico_recibe_id' => fn (array $attributes) => User::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'cliente_entrega_id' => fn (array $attributes) => Cliente::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'firma_cliente_entrega' => null,
            'estado_ingreso' => 'RECIBIDO',
            'novedad' => null,
        ];
    }

    /**
     * Configure the model factory: an ingreso without bahías (see conBahias()) gets one
     * new bahía of its tenant.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Ingreso $ingreso): void {
            if ($ingreso->bahias()->exists()) {
                return;
            }

            $area = Area::factory()->create(['tenant_id' => $ingreso->tenant_id]);
            $ingreso->bahias()->attach(Bahia::factory()->create(['area_id' => $area->id, 'tenant_id' => $ingreso->tenant_id]));
        });
    }

    /**
     * Ingreso of the given bahías (all of the same tenant), which also sets its tenant.
     */
    public function conBahias(Bahia ...$bahias): static
    {
        return $this->state(['tenant_id' => $bahias[0]->tenant_id])
            ->hasAttached(collect($bahias), [], 'bahias');
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
