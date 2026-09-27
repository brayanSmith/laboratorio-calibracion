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
            'tipo_servicio' => ['required', Rule::in(['MANTENIMIENTO', 'CALIBRACION'])],
            'intervalo_servicio' => ['nullable', 'numeric'],
            'fecha_apertura_historial_servicio' => ['nullable', 'date'],
            'fecha_ultimo_servicio' => ['nullable', 'date'],
            'fecha_proximo_servicio' => ['nullable', 'date'],
            'dias_plazo_vencimiento' => ['required', 'numeric'],
            'estado_vencimiento' => ['required', Rule::in(['AL_DIA', 'PROXIMO_A_VENCER', 'VENCIDO'])],
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
            'fecha_apertura_historial_servicio' => 'fecha de apertura del historial',
            'fecha_ultimo_servicio' => 'fecha del último servicio',
            'fecha_proximo_servicio' => 'fecha del próximo servicio',
            'dias_plazo_vencimiento' => 'días de plazo de vencimiento',
            'estado_vencimiento' => 'estado de vencimiento',
        ];
    }
}
