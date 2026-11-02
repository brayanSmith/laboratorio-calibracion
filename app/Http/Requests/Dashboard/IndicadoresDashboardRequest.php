<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros de los indicadores del dashboard (tiempos de atención, desempeño y
 * cumplimiento). Cada indicador usa los que le aplican; el permiso de cada uno lo revisa
 * IndicadoresDashboardController.
 */
class IndicadoresDashboardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            // "propio" (laboratorio propio) o el id de una empresa tercera.
            'laboratorio' => [
                'nullable',
                Rule::when($this->input('laboratorio') !== 'propio', [
                    'integer',
                    Rule::exists('empresa_terceros', 'id')->where('tenant_id', $tenantId),
                ]),
            ],
            'tecnico_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'tipo_equipo_id' => ['nullable', 'integer', Rule::exists('tipo_equipos', 'id')->where('tenant_id', $tenantId)],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('tenant_id', $tenantId)],
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
            'desde' => 'fecha desde',
            'hasta' => 'fecha hasta',
            'laboratorio' => 'laboratorio',
            'tecnico_id' => 'técnico',
            'tipo_equipo_id' => 'tipo de equipo',
            'area_id' => 'área',
        ];
    }
}
