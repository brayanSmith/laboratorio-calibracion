<?php

namespace App\Http\Requests\Despachos;

use App\Models\Despacho;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateDespachoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('despacho'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'entrega_autorizada' => $this->boolean('entrega_autorizada'),
            'entrega_recibida' => $this->boolean('entrega_recibida'),
            'eliminar_firma' => $this->boolean('eliminar_firma'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Despacho $despacho */
        $despacho = $this->route('despacho');
        $tenantId = $despacho->tenant_id;

        return [
            'tecnico_entrega_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'entrega_autorizada' => ['boolean'],
            'cliente_recibe_id' => ['nullable', Rule::exists('clientes', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'firma_cliente_recibe' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'eliminar_firma' => ['boolean'],
            'entrega_recibida' => ['boolean'],
            'novedad_id' => [
                'nullable',
                Rule::exists('novedads', 'id')->where('tenant_id', $tenantId)->where('categoria', 'SALIDA')->whereNull('deleted_at'),
            ],
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
            'tecnico_entrega_id' => 'técnico que entrega',
            'entrega_autorizada' => 'entrega autorizada',
            'cliente_recibe_id' => 'cliente que recibe',
            'firma_cliente_recibe' => 'firma del cliente',
            'entrega_recibida' => 'entrega recibida',
            'novedad_id' => 'novedad',
        ];
    }
}
