<?php

namespace App\Http\Requests\Equipos;

use App\Models\Equipo;
use App\Models\UnidadMedida;
use Closure;
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
            'alcance_indicacion' => ['required', 'string', 'max:50', $this->alcanceIndicacionConSimbolo()],
            'precision' => ['required', 'string', 'max:20', 'regex:/^(±\d+(\.\d{1,2})?|\d+(\.\d{1,2})?%)$/u'],
            'resolucion' => ['required', 'string', 'max:50', $this->resolucionConSimbolo()],
        ];
    }

    /**
     * Validate that alcance_indicacion is a number followed by the símbolo of the selected unidad de medida.
     */
    private function alcanceIndicacionConSimbolo(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $unidad = UnidadMedida::find($this->input('unidad_medida_id'));

            if (! $unidad) {
                // Otra regla ya reporta el error de unidad de medida.
                return;
            }

            $simbolo = preg_quote($unidad->simbolo, '/');

            if (! preg_match("/^\d+(\.\d{1,2})?{$simbolo}$/u", (string) $value)) {
                $fail("El alcance de indicación debe ser un número seguido del símbolo de la unidad de medida seleccionada ({$unidad->simbolo}).");
            }
        };
    }

    /**
     * Validate that resolucion is a number followed by the símbolo of the selected unidad de medida.
     */
    private function resolucionConSimbolo(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $unidad = UnidadMedida::find($this->input('unidad_medida_id'));

            if (! $unidad) {
                // Otra regla ya reporta el error de unidad de medida.
                return;
            }

            $simbolo = preg_quote($unidad->simbolo, '/');

            if (! preg_match("/^\d+(\.\d{1,2})?{$simbolo}$/u", (string) $value)) {
                $fail("La resolución debe ser un número seguido del símbolo de la unidad de medida seleccionada ({$unidad->simbolo}).");
            }
        };
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
