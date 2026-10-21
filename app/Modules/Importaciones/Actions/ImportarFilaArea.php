<?php

namespace App\Modules\Importaciones\Actions;

use App\Modules\Catalogos\Models\Area;

/**
 * Crea un área a partir de una fila del CSV.
 */
class ImportarFilaArea extends ImportadorFila
{
    public static function campos(): array
    {
        return [
            self::campo('nombre', 'Nombre', true, 'Si ya existe un área con este nombre, la fila se omite.'),
            self::campo('descripcion', 'Descripción'),
            self::campo('direccion', 'Dirección'),
        ];
    }

    public static function ejemplo(): array
    {
        return [
            'nombre' => 'Planta principal',
            'descripcion' => 'Fila de ejemplo: bórrala antes de importar',
            'direccion' => 'Calle 10 # 20-30',
        ];
    }

    public function importar(array $valores): Area
    {
        // Mismas reglas que el formulario de áreas (ver StoreAreaRequest).
        $datos = $this->validar($this->limpiar($valores), [
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ]);

        $this->reservarNombre(Area::class, $datos['nombre'], "Ya existe un área llamada {$datos['nombre']}.");

        return Area::create([...$datos, 'tenant_id' => $this->tenantId]);
    }
}
