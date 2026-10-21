<?php

namespace App\Modules\Importaciones\Actions;

use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;

/**
 * Crea una bahía a partir de una fila del CSV, dentro de un área que ya debe existir. El
 * nombre solo se repite en otra área.
 */
class ImportarFilaBahia extends ImportadorFila
{
    public static function campos(): array
    {
        return [
            self::campo('area', 'Área', true, 'Nombre de un área ya registrada.'),
            self::campo('nombre', 'Nombre', true, 'Si el área ya tiene una bahía con este nombre, la fila se omite.'),
        ];
    }

    public static function ejemplo(): array
    {
        return [
            'area' => 'Planta principal',
            'nombre' => 'Bahía 1',
        ];
    }

    public static function nota(): ?string
    {
        return 'Las áreas deben existir antes de importar sus bahías.';
    }

    public function importar(array $valores): Bahia
    {
        $datos = $this->validar($this->limpiar($valores), [
            'area' => ['required', 'string', 'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $areaId = $this->idExistente(Area::class, $datos['area'], 'area', "No existe el área {$datos['area']}; créala o impórtala primero.");

        $this->reservarNombre(
            Bahia::class,
            $datos['nombre'],
            "El área {$datos['area']} ya tiene una bahía llamada {$datos['nombre']}.",
            ['area_id' => (string) $areaId],
        );

        return Bahia::create([
            'nombre' => $datos['nombre'],
            'area_id' => $areaId,
            'tenant_id' => $this->tenantId,
        ]);
    }
}
