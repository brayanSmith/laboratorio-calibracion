<?php

namespace App\Modules\AlcancesMedicion\Http\Requests;

use App\Modules\AlcancesMedicion\Models\MedicionAlcance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreMedicionAlcanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', MedicionAlcance::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        $porcentaje = MedicionAlcance::simboloPrecision(
            $tenantId,
            $this->input('tipo_equipo_id'),
            $this->input('modelo'),
            $this->input('alcance_indicacion'),
        ) === '%';

        return [
            'tipo_equipo_id' => [
                'required',
                'integer',
                Rule::exists('tipo_equipos', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'modelo' => [
                'required',
                'string',
                'max:255',
                Rule::exists('equipos', 'modelo')
                    ->where('tenant_id', $tenantId)
                    ->where('tipo_equipo_id', $this->input('tipo_equipo_id'))
                    ->whereNull('deleted_at'),
            ],
            'alcance_indicacion' => [
                'required',
                'string',
                'max:255',
                Rule::exists('equipo_especificacion_tecnicas', 'alcance_indicacion')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereIn('equipo_id', fn ($query) => $query->select('id')
                        ->from('equipos')
                        ->where('tipo_equipo_id', $this->input('tipo_equipo_id'))
                        ->where('modelo', $this->input('modelo'))
                        ->whereNull('deleted_at')),
                Rule::unique('medicion_alcances', 'alcance_indicacion')
                    ->where('tenant_id', $tenantId)
                    ->where('tipo_equipo_id', $this->input('tipo_equipo_id'))
                    ->where('modelo', $this->input('modelo'))
                    ->whereNull('deleted_at'),
            ],
            'detalles' => ['nullable', 'array', 'max:50'],
            'detalles.*.unidad_medida_id' => [
                'required',
                'integer',
                Rule::exists('unidad_medidas', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'detalles.*.valor_referencia' => ['required', 'numeric', 'between:-99999999.99,99999999.99'],
            'detalles.*.emp' => [Rule::requiredIf($porcentaje === false), 'nullable', 'numeric', 'between:0,99999999.99'],
            'detalles.*.emp_porcentaje' => [Rule::requiredIf($porcentaje), 'nullable', 'numeric', 'between:0,100'],
            'detalles.*.incertidumbre' => ['required', 'numeric', 'between:0,99999999.99'],
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
            'tipo_equipo_id' => 'tipo de equipo',
            'modelo' => 'modelo',
            'alcance_indicacion' => 'alcance de indicación',
            'detalles.*.unidad_medida_id' => 'unidad de medida',
            'detalles.*.valor_referencia' => 'valor de referencia',
            'detalles.*.emp' => 'EMP',
            'detalles.*.emp_porcentaje' => 'EMP (%)',
            'detalles.*.incertidumbre' => 'incertidumbre',
        ];
    }
}
