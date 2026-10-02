<?php

namespace App\Http\Requests\Ingresos;

use App\Models\Ingreso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateEstadoIngresoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('ingreso'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['eliminar_firma' => $this->boolean('eliminar_firma')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Usado tanto por "Recibir equipos" (estado_ingreso = INGRESADO) como por
     * "Cancelar ingreso" (estado_ingreso = CANCELADO), y también para volver un
     * ingreso a PENDIENTE.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Ingreso $ingreso */
        $ingreso = $this->route('ingreso');
        $tenantId = $ingreso->tenant_id;

        return [
            'estado_ingreso' => ['required', Rule::in(['PENDIENTE', 'INGRESADO', 'CANCELADO'])],
            'tecnico_recibe_id' => ['nullable', 'required_if:estado_ingreso,INGRESADO', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'cliente_entrega_id' => ['nullable', 'required_if:estado_ingreso,INGRESADO', Rule::exists('clientes', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'firma_cliente_entrega' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'eliminar_firma' => ['boolean'],
            'novedad' => ['nullable', 'string', 'max:5000'],
            'motivo_cancelacion' => ['nullable', 'required_if:estado_ingreso,CANCELADO', 'string', 'max:5000'],
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
            'estado_ingreso' => 'estado del ingreso',
            'tecnico_recibe_id' => 'técnico que recibe',
            'cliente_entrega_id' => 'cliente que entrega',
            'firma_cliente_entrega' => 'firma del cliente',
            'novedad' => 'novedad',
            'motivo_cancelacion' => 'motivo de cancelación',
        ];
    }
}
