<?php

namespace App\Http\Requests\Equipos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreEquipoProgramacionRequest extends FormRequest
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
        return [
            'tipo_servicio' => ['required', 'array', 'min:1'],
            'tipo_servicio.*' => [Rule::in(['MANTENIMIENTO', 'CALIBRACION'])],
            'intervalo_servicio' => ['nullable', 'numeric'],
            'intervalo_unidad' => ['required_with:intervalo_servicio', Rule::in(['DIAS', 'SEMANAS', 'MESES'])],
            'fecha_ultimo_servicio' => ['nullable', 'date'],
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
            'tipo_servicio' => 'tipo de servicio',
            'intervalo_servicio' => 'intervalo de servicio',
            'intervalo_unidad' => 'unidad del intervalo',
            'fecha_ultimo_servicio' => 'fecha del último servicio',
        ];
    }
}
