<?php

namespace App\Http\Requests\Equipos;

use App\Models\Equipo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateEquipoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('equipo'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'activo' => $this->boolean('activo'),
            'patron_referencia' => $this->boolean('patron_referencia'),
            'requiere_programacion' => $this->boolean('requiere_programacion'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Equipo $equipo */
        $equipo = $this->route('equipo');
        $tenantId = $equipo->tenant_id;

        return [
            'codigo' => ['required', 'string', 'max:255', Rule::unique('equipos', 'codigo')->ignore($equipo->id)],
            'tipo_equipo_id' => ['required', Rule::exists('tipo_equipos', 'id')->where('tenant_id', $tenantId)],
            'tipo_tecnologia' => ['required', 'in:ANALOGICO,DIGITAL'],
            'modelo' => ['required', 'string', 'max:255'],
            'fabricante_id' => ['required', Rule::exists('fabricantes', 'id')->where('tenant_id', $tenantId)],
            'numero_serie' => ['required', 'string', 'max:255'],
            'area_id' => ['required', Rule::exists('areas', 'id')->where('tenant_id', $tenantId)],
            'bahia_id' => ['required', Rule::exists('bahias', 'id')->where('tenant_id', $tenantId)],
            'condicion_actual' => ['required', 'string', 'max:255'],
            'notas' => ['nullable', 'string'],
            'activo' => ['boolean'],
            'patron_referencia' => ['boolean'],
            'concatenar_codigo_nombre' => ['nullable', 'string', 'max:255'],
            'requiere_programacion' => ['boolean'],
            'cliente_id' => ['required', Rule::exists('clientes', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
