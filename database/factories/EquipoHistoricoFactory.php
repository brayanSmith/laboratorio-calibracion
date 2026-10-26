<?php

namespace Database\Factories;

use App\Modules\Equipos\Models\EquipoHistorico;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Se usa con el equipo al que pertenece, del que toma el tenant:
 * EquipoHistorico::factory()->for($equipo)->create().
 *
 * @extends Factory<EquipoHistorico>
 */
class EquipoHistoricoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_servicio' => fake()->randomElement(EquipoHistorico::TIPOS_SERVICIO),
            'fecha_servicio' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'es_tercero' => false,
            'empresa_tercero_id' => null,
            'adjunto' => null,
        ];
    }

    /**
     * Servicio hecho por una empresa tercera (del tenant del equipo, si no se indica una).
     */
    public function deTercero(?EmpresaTercero $empresaTercero = null): static
    {
        return $this->state(['es_tercero' => true])
            ->afterMaking(function (EquipoHistorico $historico) use ($empresaTercero): void {
                $historico->empresa_tercero_id ??= ($empresaTercero ?? EmpresaTercero::factory()->create([
                    'tenant_id' => $historico->equipo->tenant_id,
                ]))->id;
            });
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (EquipoHistorico $historico): void {
            $historico->tenant_id ??= $historico->equipo->tenant_id;
        });
    }
}
