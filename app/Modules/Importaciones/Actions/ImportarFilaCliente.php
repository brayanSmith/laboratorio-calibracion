<?php

namespace App\Modules\Importaciones\Actions;

use App\Enums\TipoDocumentoCliente;
use App\Modules\Catalogos\Models\Cliente;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Crea un cliente a partir de una fila del CSV.
 */
class ImportarFilaCliente extends ImportadorFila
{
    public static function campos(): array
    {
        return [
            self::campo('nombre', 'Nombre', true),
            self::campo('tipo_documento', 'Tipo de documento', false, 'NIT, CC, CE o PASAPORTE. Por defecto: NIT.'),
            self::campo('numero_documento', 'Número de documento', true, 'Único: si ya existe un cliente con este documento, la fila se omite.'),
            self::campo('email', 'Correo electrónico', true, 'Único entre los clientes.'),
            self::campo('telefono', 'Teléfono'),
            self::campo('direccion', 'Dirección'),
        ];
    }

    public static function ejemplo(): array
    {
        return [
            'nombre' => 'Cliente de ejemplo',
            'tipo_documento' => 'NIT',
            'numero_documento' => '900123456',
            'email' => 'cliente@ejemplo.com',
            'telefono' => '601 555 1234',
            'direccion' => 'Fila de ejemplo: bórrala antes de importar',
        ];
    }

    public function importar(array $valores): Cliente
    {
        $datos = $this->limpiar($valores);
        $datos['tipo_documento'] = $this->tipoDocumento($datos['tipo_documento']);

        // Mismas reglas que el formulario de clientes (ver StoreClienteRequest).
        $datos = $this->validar($datos, [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo_documento' => ['required', Rule::enum(TipoDocumentoCliente::class)],
            'numero_documento' => [
                'required',
                'string',
                'max:50',
                // Sin excluir los eliminados: el índice único de la tabla también los cuenta.
                Rule::unique('clientes', 'numero_documento')->where('tenant_id', $this->tenantId),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('clientes', 'email')->where('tenant_id', $this->tenantId)->whereNull('deleted_at'),
            ],
            'telefono' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ], [
            'tipo_documento.enum' => 'El tipo de documento debe ser NIT, CC, CE o PASAPORTE.',
            'numero_documento.unique' => 'Ya existe un cliente con el documento :input.',
            'email.unique' => 'Ya existe un cliente con el correo :input.',
        ]);

        return Cliente::create([...$datos, 'tenant_id' => $this->tenantId]);
    }

    /**
     * Acepta el código o el nombre del tipo de documento ("cc", "Cédula de ciudadanía");
     * vacío es NIT. Un valor que no se reconoce se deja tal cual para que la validación lo
     * rechace.
     */
    private function tipoDocumento(?string $valor): string
    {
        if ($valor === null) {
            return TipoDocumentoCliente::Nit->value;
        }

        foreach (TipoDocumentoCliente::cases() as $tipo) {
            if (in_array(self::clave($valor), [self::clave($tipo->value), self::clave($tipo->label())], true)) {
                return $tipo->value;
            }
        }

        return Str::upper($valor);
    }
}
