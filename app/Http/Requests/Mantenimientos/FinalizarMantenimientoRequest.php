<?php

namespace App\Http\Requests\Mantenimientos;

use App\Models\Mantenimiento;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FinalizarMantenimientoRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'estado_final_equipo' => ['required', Rule::in(['OPERATIVO', 'FUERA_DE_SERVICIO'])],
            'firmado' => ['boolean'],
        ];
    }

    /**
     * Un mantenimiento al que le faltan repuestos no se puede finalizar.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Mantenimiento $mantenimiento */
            $mantenimiento = $this->route('mantenimiento');

            if ($mantenimiento->estado_mantenimiento === 'FALTA_REPUESTOS') {
                $validator->errors()->add('estado_mantenimiento', __('No se puede finalizar un mantenimiento al que le faltan repuestos.'));
            }
        }];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'estado_final_equipo' => 'estado final del equipo',
            'firmado' => 'firmado',
        ];
    }
}
