<?php

namespace App\Modules\Catalogos\Http\Requests;

use App\Enums\TipoDocumentoCliente;
use App\Modules\Catalogos\Models\Cliente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Cliente::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo_documento' => ['required', Rule::enum(TipoDocumentoCliente::class)],
            'numero_documento' => [
                'required',
                'string',
                'max:50',
                // Sin excluir los eliminados: el índice único de la tabla también los cuenta.
                Rule::unique('clientes', 'numero_documento')
                    ->where('tenant_id', $this->user()->tenant_id),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('clientes', 'email')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->whereNull('deleted_at'),
            ],
            'telefono' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'tipo_documento' => 'tipo de documento',
            'numero_documento' => 'número de documento',
            'email' => 'correo electrónico',
            'telefono' => 'teléfono',
            'direccion' => 'dirección',
        ];
    }
}
