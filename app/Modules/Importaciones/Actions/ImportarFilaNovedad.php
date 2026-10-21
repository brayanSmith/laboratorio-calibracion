<?php

namespace App\Modules\Importaciones\Actions;

use App\Modules\Catalogos\Models\Novedad;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Crea una novedad a partir de una fila del CSV. El nombre no se repite dentro de la misma
 * categoría.
 */
class ImportarFilaNovedad extends ImportadorFila
{
    public static function campos(): array
    {
        return [
            self::campo('categoria', 'Categoría', true, 'INGRESO, MANTENIMIENTO, CALIBRACION o SALIDA.'),
            self::campo('nombre', 'Nombre', true, 'Si ya existe una novedad con este nombre en la categoría, la fila se omite.'),
        ];
    }

    public static function ejemplo(): array
    {
        return [
            'categoria' => 'INGRESO',
            'nombre' => 'Llegó sin accesorios',
        ];
    }

    public function importar(array $valores): Novedad
    {
        $datos = $this->limpiar($valores);

        if ($datos['categoria'] !== null) {
            // "Calibración" o "salida" => "CALIBRACION" o "SALIDA".
            $datos['categoria'] = Str::upper(Str::ascii($datos['categoria']));
        }

        $datos = $this->validar($datos, [
            'categoria' => ['required', 'string', Rule::in(Novedad::CATEGORIAS)],
            'nombre' => ['required', 'string', 'max:255'],
        ], [
            'categoria.in' => 'La categoría debe ser INGRESO, MANTENIMIENTO, CALIBRACION o SALIDA.',
        ]);

        $this->reservarNombre(
            Novedad::class,
            $datos['nombre'],
            "Ya existe una novedad llamada {$datos['nombre']} en la categoría {$datos['categoria']}.",
            ['categoria' => $datos['categoria']],
        );

        return Novedad::create([...$datos, 'tenant_id' => $this->tenantId]);
    }
}
