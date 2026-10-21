<?php

namespace App\Modules\Importaciones\Actions;

use App\Modules\Calibraciones\Models\Laboratorio;

/**
 * Crea un laboratorio a partir de una fila del CSV. Ninguno queda como principal: se marca
 * desde el formulario del laboratorio.
 */
class ImportarFilaLaboratorio extends ImportadorFila
{
    public static function campos(): array
    {
        return [
            self::campo('nombre', 'Nombre', true, 'Si ya existe un laboratorio con este nombre, la fila se omite.'),
            self::campo('descripcion', 'Descripción'),
            self::campo('direccion', 'Dirección'),
            self::campo('email', 'Correo electrónico'),
            self::campo('telefono', 'Teléfono'),
        ];
    }

    public static function ejemplo(): array
    {
        return [
            'nombre' => 'Laboratorio de presión',
            'descripcion' => 'Fila de ejemplo: bórrala antes de importar',
            'direccion' => 'Piso 2',
            'email' => 'presion@laboratorio.com',
            'telefono' => '601 555 1234',
        ];
    }

    public function importar(array $valores): Laboratorio
    {
        // Mismas reglas que el formulario de laboratorios (ver StoreLaboratorioRequest).
        $datos = $this->validar($this->limpiar($valores), [
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
        ]);

        $this->reservarNombre(Laboratorio::class, $datos['nombre'], "Ya existe un laboratorio llamado {$datos['nombre']}.");

        return Laboratorio::create([...$datos, 'tenant_id' => $this->tenantId]);
    }
}
