<?php

namespace Database\Factories;

use App\Enums\TipoDocumentoCliente;
use App\Models\Tenant;
use App\Modules\Catalogos\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'tipo_documento' => fake()->randomElement(TipoDocumentoCliente::cases()),
            'numero_documento' => fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->optional()->phoneNumber(),
            'direccion' => fake()->optional()->address(),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
