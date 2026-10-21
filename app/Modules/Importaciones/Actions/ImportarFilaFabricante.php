<?php

namespace App\Modules\Importaciones\Actions;

use App\Modules\Catalogos\Models\Fabricante;

/**
 * Crea un fabricante a partir de una fila del CSV.
 */
class ImportarFilaFabricante extends ImportadorFila
{
    public static function campos(): array
    {
        return [
            self::campo('nombre', 'Nombre', true, 'Si ya existe un fabricante con este nombre, la fila se omite.'),
        ];
    }

    public static function ejemplo(): array
    {
        return ['nombre' => 'Fluke'];
    }

    public function importar(array $valores): Fabricante
    {
        $datos = $this->validar($this->limpiar($valores), [
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $this->reservarNombre(Fabricante::class, $datos['nombre'], "Ya existe un fabricante llamado {$datos['nombre']}.");

        return Fabricante::create([...$datos, 'tenant_id' => $this->tenantId]);
    }
}
