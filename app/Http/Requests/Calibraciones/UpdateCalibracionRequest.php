<?php

namespace App\Http\Requests\Calibraciones;

use App\Models\Calibracion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCalibracionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('calibracion'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'ajustes_requeridos' => $this->boolean('ajustes_requeridos'),
            'firmado' => $this->boolean('firmado'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Calibracion $calibracion */
        $calibracion = $this->route('calibracion');
        $tenantId = $calibracion->tenant_id;

        return [
            'tecnico_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'laboratorio_id' => [
                'nullable',
                Rule::exists('laboratorios', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'solicitante_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'procedimiento_id' => [
                'nullable',
                Rule::exists('procedimiento_calibracions', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'temperatura' => ['nullable', 'numeric'],
            'humedad' => ['nullable', 'numeric'],
            'ajustes_requeridos' => ['boolean'],
            'estado_calibracion' => ['required', Rule::in(['PENDIENTE', 'EN_PROCESO', 'FINALIZADO', 'DEVOLVER_MANTENIMIENTO'])],
            'firmado' => ['boolean'],
            'novedad_id' => [
                'nullable',
                Rule::exists('novedads', 'id')->where('tenant_id', $tenantId)->where('categoria', 'CALIBRACION')->whereNull('deleted_at'),
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
            'tecnico_id' => 'técnico',
            'laboratorio_id' => 'laboratorio',
            'solicitante_id' => 'solicitante',
            'procedimiento_id' => 'procedimiento',
            'temperatura' => 'temperatura',
            'humedad' => 'humedad',
            'estado_calibracion' => 'estado de la calibración',
            'novedad_id' => 'novedad',
        ];
    }
}
