<?php

namespace App\Modules\Importaciones\Http\Requests;

use App\Modules\Equipos\Models\Equipo;
use App\Modules\Importaciones\Support\CamposImportacionEquipo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreImportacionEquipoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Equipo::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = [
            // Nombre con el que se guardó el CSV al leer sus encabezados (ver encabezados()).
            'archivo' => ['required', 'string', 'regex:/^[A-Za-z0-9]{40}\.csv$/'],
            'nombre_original' => ['required', 'string', 'max:255'],
            'mapeo' => ['required', 'array:'.implode(',', CamposImportacionEquipo::claves())],
            'mapeo.*' => ['nullable', 'integer', 'min:0'],
        ];

        foreach (CamposImportacionEquipo::requeridos() as $clave) {
            $reglas["mapeo.{$clave}"] = ['required', 'integer', 'min:0'];
        }

        return $reglas;
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $atributos = [];

        foreach (CamposImportacionEquipo::todos() as $campo) {
            $atributos["mapeo.{$campo['clave']}"] = $campo['etiqueta'];
        }

        return $atributos;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mapeo.*.required' => 'Elige la columna del CSV para :attribute.',
        ];
    }
}
