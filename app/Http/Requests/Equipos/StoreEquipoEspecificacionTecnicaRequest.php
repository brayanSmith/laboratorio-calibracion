<?php

namespace App\Http\Requests\Equipos;

use App\Models\Equipo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreEquipoEspecificacionTecnicaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('equipo'));
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

        return [
            'tipo_magnitud_id' => [
                'required',
                Rule::exists('tipo_magnituds', 'id')->where('tenant_id', $equipo->tenant_id),
            ],
            'unidad_medida_id' => [
                'required',
                Rule::exists('unidad_medidas', 'id')->where('tenant_id', $equipo->tenant_id),
            ],
            'alcance_indicacion' => ['required', 'numeric'],
            'precision' => ['required', 'numeric'],
            'resolucion' => ['required', 'numeric'],
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
            'tipo_magnitud_id' => 'tipo de magnitud',
            'unidad_medida_id' => 'unidad de medida',
            'alcance_indicacion' => 'alcance de indicación',
            'precision' => 'precisión',
            'resolucion' => 'resolución',
        ];
    }
}
