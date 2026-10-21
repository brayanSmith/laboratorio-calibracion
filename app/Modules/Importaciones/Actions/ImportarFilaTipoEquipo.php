<?php

namespace App\Modules\Importaciones\Actions;

use App\Modules\Catalogos\Models\TipoEquipo;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Crea un tipo de equipo a partir de una fila del CSV.
 */
class ImportarFilaTipoEquipo extends ImportadorFila
{
    public static function campos(): array
    {
        return [
            self::campo('nombre', 'Nombre', true, 'Si ya existe un tipo de equipo con este nombre, la fila se omite.'),
            self::campo('tipo_mantenimiento', 'Tipo de mantenimiento', false, 'A (mantenimiento y calibración) o B (solo mantenimiento). Por defecto: A.'),
        ];
    }

    public static function ejemplo(): array
    {
        return [
            'nombre' => 'Manómetro',
            'tipo_mantenimiento' => 'A',
        ];
    }

    public function importar(array $valores): TipoEquipo
    {
        $datos = $this->limpiar($valores);
        $datos['tipo_mantenimiento'] = Str::upper($datos['tipo_mantenimiento'] ?? 'A');

        $datos = $this->validar($datos, [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo_mantenimiento' => ['required', Rule::in(['A', 'B'])],
        ], [
            'tipo_mantenimiento.in' => 'El tipo de mantenimiento debe ser A o B.',
        ]);

        $this->reservarNombre(TipoEquipo::class, $datos['nombre'], "Ya existe un tipo de equipo llamado {$datos['nombre']}.");

        return TipoEquipo::create([...$datos, 'tenant_id' => $this->tenantId]);
    }
}
