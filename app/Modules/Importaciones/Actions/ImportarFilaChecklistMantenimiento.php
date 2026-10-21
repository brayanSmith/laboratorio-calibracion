<?php

namespace App\Modules\Importaciones\Actions;

use App\Modules\Catalogos\Models\TipoEquipo;
use App\Modules\Catalogos\Models\TipoEquipoCheckList;

/**
 * Agrega un punto al checklist de mantenimiento de un tipo de equipo que ya debe existir.
 * El nombre solo se repite en el checklist de otro tipo de equipo.
 */
class ImportarFilaChecklistMantenimiento extends ImportadorFila
{
    public static function campos(): array
    {
        return [
            self::campo('tipo_equipo', 'Tipo de equipo', true, 'Nombre de un tipo de equipo ya registrado.'),
            self::campo('nombre', 'Punto del checklist', true, 'Si el tipo de equipo ya lo tiene, la fila se omite.'),
        ];
    }

    public static function ejemplo(): array
    {
        return [
            'tipo_equipo' => 'Manómetro',
            'nombre' => 'Revisar estado de la carátula',
        ];
    }

    public static function nota(): ?string
    {
        return 'Los tipos de equipo deben existir antes de importar su checklist.';
    }

    public function importar(array $valores): TipoEquipoCheckList
    {
        $datos = $this->validar($this->limpiar($valores), [
            'tipo_equipo' => ['required', 'string', 'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $tipoEquipoId = $this->idExistente(
            TipoEquipo::class,
            $datos['tipo_equipo'],
            'tipo_equipo',
            "No existe el tipo de equipo {$datos['tipo_equipo']}; créalo o impórtalo primero.",
        );

        $this->reservarNombre(
            TipoEquipoCheckList::class,
            $datos['nombre'],
            "El tipo de equipo {$datos['tipo_equipo']} ya tiene en su checklist: {$datos['nombre']}.",
            ['tipo_equipo_id' => (string) $tipoEquipoId],
        );

        return TipoEquipoCheckList::create([
            'tipo_equipo_id' => $tipoEquipoId,
            'nombre' => $datos['nombre'],
            'tenant_id' => $this->tenantId,
        ]);
    }
}
