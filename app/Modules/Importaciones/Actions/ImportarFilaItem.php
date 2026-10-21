<?php

namespace App\Modules\Importaciones\Actions;

use App\Modules\Inventario\Models\Item;
use Illuminate\Validation\Rule;

/**
 * Crea un ítem del inventario a partir de una fila del CSV. El stock no se importa: sale de
 * las entradas y de lo usado en los mantenimientos.
 */
class ImportarFilaItem extends ImportadorFila
{
    public static function campos(): array
    {
        return [
            self::campo('codigo', 'Código', true, 'Único: si ya existe un ítem con este código, la fila se omite.'),
            self::campo('nombre', 'Nombre', true),
            self::campo('descripcion', 'Descripción'),
            self::campo('ubicacion', 'Ubicación'),
            self::campo('precio_unitario', 'Precio unitario', false, 'Número, con coma o punto decimal (ej. 12500,50).'),
        ];
    }

    public static function ejemplo(): array
    {
        return [
            'codigo' => 'REP-0001',
            'nombre' => 'Empaque de nitrilo',
            'descripcion' => 'Fila de ejemplo: bórrala antes de importar',
            'ubicacion' => 'Estante A1',
            'precio_unitario' => '12500,50',
        ];
    }

    public static function nota(): ?string
    {
        return 'El stock no se importa: se registra con las entradas de ítems.';
    }

    public function importar(array $valores): Item
    {
        $datos = $this->limpiar($valores);

        if ($datos['precio_unitario'] !== null) {
            $datos['precio_unitario'] = self::numero($datos['precio_unitario']);
        }

        // Mismas reglas que el formulario de ítems (ver StoreItemRequest).
        $datos = $this->validar($datos, [
            'codigo' => [
                'required',
                'string',
                'max:255',
                Rule::unique('items', 'codigo')->where('tenant_id', $this->tenantId),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'precio_unitario' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ], [
            'codigo.unique' => 'Ya existe un ítem con el código :input.',
        ]);

        return Item::create([...$datos, 'tenant_id' => $this->tenantId]);
    }
}
