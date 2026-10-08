<?php

namespace App\Http\Requests\AlcancesMedicion;

use App\Models\MedicionAlcance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMedicionAlcanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('medicionAlcance'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var MedicionAlcance $medicionAlcance */
        $medicionAlcance = $this->route('medicionAlcance');

        return [
            'tipo_equipo_id' => [
                'required',
                'integer',
                Rule::exists('tipo_equipos', 'id')
                    ->where('tenant_id', $medicionAlcance->tenant_id)
                    ->whereNull('deleted_at'),
            ],
            'alcance_indicacion' => [
                'required',
                'string',
                'max:255',
                $this->input('alcance_indicacion') === $medicionAlcance->alcance_indicacion
                    && (int) $this->input('tipo_equipo_id') === $medicionAlcance->tipo_equipo_id
                    ? 'string'
                    : Rule::exists('equipo_especificacion_tecnicas', 'alcance_indicacion')
                        ->where('tenant_id', $medicionAlcance->tenant_id)
                        ->whereNull('deleted_at')
                        ->whereIn('equipo_id', fn ($query) => $query->select('id')
                            ->from('equipos')
                            ->where('tipo_equipo_id', $this->input('tipo_equipo_id'))
                            ->whereNull('deleted_at')),
                Rule::unique('medicion_alcances', 'alcance_indicacion')
                    ->where('tenant_id', $medicionAlcance->tenant_id)
                    ->where('tipo_equipo_id', $this->input('tipo_equipo_id'))
                    ->whereNull('deleted_at')
                    ->ignore($medicionAlcance->id),
            ],
            'sincronizar_detalles' => ['sometimes', 'boolean'],
            'detalles' => ['nullable', 'array', 'max:200'],
            'detalles.*.id' => [
                'nullable',
                'integer',
                Rule::exists('detalle_medicion_alcances', 'id')
                    ->where('medicion_alcance_id', $medicionAlcance->id)
                    ->whereNull('deleted_at'),
            ],
            'detalles.*.unidad_medida_id' => [
                'required',
                'integer',
                Rule::exists('unidad_medidas', 'id')
                    ->where('tenant_id', $medicionAlcance->tenant_id)
                    ->whereNull('deleted_at'),
            ],
            'detalles.*.valor_instrumento' => ['required', 'numeric', 'between:-99999999.99,99999999.99'],
            'detalles.*.emp' => ['required', 'numeric', 'between:0,99999999.99'],
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
            'alcance_indicacion' => 'alcance de indicación',
            'detalles.*.unidad_medida_id' => 'unidad de medida',
            'detalles.*.valor_instrumento' => 'valor del instrumento',
            'detalles.*.emp' => 'EMP',
            'detalles.*.incertidumbre' => 'incertidumbre',
        ];
    }
}
