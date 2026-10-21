<?php

namespace App\Modules\Importaciones\Actions;

use App\Modules\Calibraciones\Models\ProcedimientoCalibracion;

/**
 * Crea un procedimiento de calibración a partir de una fila del CSV.
 */
class ImportarFilaProcedimientoCalibracion extends ImportadorFila
{
    public static function campos(): array
    {
        return [
            self::campo('nombre', 'Nombre', true, 'Si ya existe un procedimiento con este nombre, la fila se omite.'),
        ];
    }

    public static function ejemplo(): array
    {
        return ['nombre' => 'Comparación directa con patrón'];
    }

    public function importar(array $valores): ProcedimientoCalibracion
    {
        $datos = $this->validar($this->limpiar($valores), [
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $this->reservarNombre(ProcedimientoCalibracion::class, $datos['nombre'], "Ya existe un procedimiento de calibración llamado {$datos['nombre']}.");

        return ProcedimientoCalibracion::create([...$datos, 'tenant_id' => $this->tenantId]);
    }
}
