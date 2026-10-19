<?php

namespace Database\Factories;

use App\Modules\Inventario\Models\EntradaItem;
use App\Modules\Inventario\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntradaItem>
 */
class EntradaItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 20);
        $precioUnitario = fake()->randomFloat(2, 1, 500);

        return [
            'item_id' => Item::factory(),
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'precio_total' => round($cantidad * $precioUnitario, 2),
            'codigo_compra' => fake()->optional()->bothify('OC-####'),
            'recibido' => false,
            'fecha_recibido' => null,
            'observaciones' => fake()->optional()->sentence(),
            'tenant_id' => fn (array $attributes) => Item::find($attributes['item_id'])->tenant_id,
        ];
    }

    /**
     * Indicate that the entrada was already received.
     */
    public function recibido(): static
    {
        return $this->state(fn () => [
            'recibido' => true,
            'fecha_recibido' => now()->toDateString(),
        ]);
    }
}
