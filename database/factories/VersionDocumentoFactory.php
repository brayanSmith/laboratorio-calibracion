<?php

namespace Database\Factories;

use App\Models\VersionDocumento;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VersionDocumento>
 */
class VersionDocumentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Necesita el documento: ->for($mantenimiento, 'documentable') o ->for($calibracion, 'documentable').
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero' => 0,
            'codigo' => fn (array $atributos) => VersionDocumento::codigoBase($atributos['documentable_type'] ?? Mantenimiento::class, (int) ($atributos['documentable_id'] ?? 0)),
            'ruta' => 'informes/'.fake()->uuid().'.pdf',
            'tenant_id' => fn (array $atributos) => $atributos['documentable_type']::query()->find($atributos['documentable_id'])?->tenant_id,
        ];
    }
}
