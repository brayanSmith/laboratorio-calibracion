<?php

namespace App\Modules\Importaciones\Actions;

use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use Illuminate\Validation\Rule;

/**
 * Crea una empresa tercera a partir de una fila del CSV.
 */
class ImportarFilaEmpresaTercero extends ImportadorFila
{
    public static function campos(): array
    {
        return [
            self::campo('nombre', 'Nombre', true),
            self::campo('nit', 'NIT', true, 'Único: si ya existe una empresa con este NIT, la fila se omite.'),
            self::campo('direccion', 'Dirección'),
            self::campo('telefono', 'Teléfono'),
            self::campo('email', 'Correo electrónico'),
        ];
    }

    public static function ejemplo(): array
    {
        return [
            'nombre' => 'Calibraciones SAS',
            'nit' => '900555666',
            'direccion' => 'Fila de ejemplo: bórrala antes de importar',
            'telefono' => '601 555 1234',
            'email' => 'contacto@calibraciones.com',
        ];
    }

    public function importar(array $valores): EmpresaTercero
    {
        // Mismas reglas que el formulario de empresas terceras (ver StoreEmpresaTerceroRequest).
        $datos = $this->validar($this->limpiar($valores), [
            'nombre' => ['required', 'string', 'max:255'],
            'nit' => [
                'required',
                'string',
                'max:255',
                Rule::unique('empresa_terceros', 'nit')->where('tenant_id', $this->tenantId),
            ],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ], [
            'nit.unique' => 'Ya existe una empresa tercera con el NIT :input.',
        ]);

        return EmpresaTercero::create([...$datos, 'tenant_id' => $this->tenantId]);
    }
}
