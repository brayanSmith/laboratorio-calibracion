<?php

namespace App\Http\Requests\AlcancesMedicion;

use App\Models\DetalleMedicionAlcance;
use App\Models\MedicionAlcance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateDetalleMedicionAlcanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('detalleMedicionAlcance'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var DetalleMedicionAlcance $detalle */
        $detalle = $this->route('detalleMedicionAlcance');

        $medicionAlcance = $detalle->medicionAlcance;
        $porcentaje = MedicionAlcance::simboloPrecision(
            $medicionAlcance->tenant_id,
            $medicionAlcance->tipo_equipo_id,
            $medicionAlcance->modelo,
            $medicionAlcance->alcance_indicacion,
        ) === '%';

        return [
            'unidad_medida_id' => [
                'required',
                'integer',
                Rule::exists('unidad_medidas', 'id')
                    ->where('tenant_id', $detalle->tenant_id)
                    ->whereNull('deleted_at'),
            ],
            'valor_referencia' => ['required', 'numeric', 'between:-99999999.99,99999999.99'],
            'emp' => [Rule::requiredIf($porcentaje === false), 'nullable', 'numeric', 'between:0,99999999.99'],
            'emp_porcentaje' => [Rule::requiredIf($porcentaje), 'nullable', 'numeric', 'between:0,100'],
            'incertidumbre' => ['required', 'numeric', 'between:0,99999999.99'],
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
            'unidad_medida_id' => 'unidad de medida',
            'valor_referencia' => 'valor de referencia',
            'emp' => 'EMP',
            'emp_porcentaje' => 'EMP (%)',
            'incertidumbre' => 'incertidumbre',
        ];
    }
}
