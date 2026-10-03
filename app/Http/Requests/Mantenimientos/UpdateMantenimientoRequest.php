<?php

namespace App\Http\Requests\Mantenimientos;

use App\Models\Mantenimiento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMantenimientoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('mantenimiento'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['firmado' => $this->boolean('firmado')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * tipo_mantenimiento no se valida aquí: se deriva de la programación que originó la
     * orden de trabajo y no se edita desde este formulario (ver OrdenTrabajoController).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Mantenimiento $mantenimiento */
        $mantenimiento = $this->route('mantenimiento');
        $tenantId = $mantenimiento->tenant_id;

        return [
            'fecha_mantenimiento' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'estado_inicial_equipo' => ['nullable', Rule::in(['OPERATIVO', 'FUERA_DE_SERVICIO'])],
            'estado_final_equipo' => ['nullable', Rule::in(['OPERATIVO', 'FUERA_DE_SERVICIO'])],
            'estado_mantenimiento' => ['required', Rule::in(['PENDIENTE', 'EN_PROCESO', 'FALTA_REPUESTOS', 'FINALIZADO'])],
            'tecnico_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'firmado' => ['boolean'],
            'novedad_id' => [
                'nullable',
                Rule::exists('novedads', 'id')->where('tenant_id', $tenantId)->where('categoria', 'MANTENIMIENTO')->whereNull('deleted_at'),
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
            'fecha_mantenimiento' => 'fecha del mantenimiento',
            'descripcion' => 'descripción',
            'estado_inicial_equipo' => 'estado inicial del equipo',
            'estado_final_equipo' => 'estado final del equipo',
            'estado_mantenimiento' => 'estado del mantenimiento',
            'tecnico_id' => 'técnico',
            'novedad_id' => 'novedad',
        ];
    }
}
