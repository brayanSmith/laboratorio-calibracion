<?php

namespace App\Http\Requests\AlcancesMedicion;

use App\Models\DetalleMedicionAlcance;
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

        return [
            'unidad_medida_id' => [
                'required',
                'integer',
                Rule::exists('unidad_medidas', 'id')
                    ->where('tenant_id', $detalle->tenant_id)
                    ->whereNull('deleted_at'),
            ],
            'valor_instrumento' => ['required', 'numeric', 'between:-99999999.99,99999999.99'],
            'emp' => ['required', 'numeric', 'between:0,99999999.99'],
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
            'valor_instrumento' => 'valor del instrumento',
            'emp' => 'EMP',
            'incertidumbre' => 'incertidumbre',
        ];
    }
}
