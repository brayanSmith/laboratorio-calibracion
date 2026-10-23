<?php

namespace App\Modules\Equipos\Http\Requests;

use App\Modules\Equipos\Models\EquipoHistorico;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreEquipoHistoricoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('agregarHistorico', $this->route('equipo'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['es_tercero' => $this->boolean('es_tercero')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipo_servicio' => ['required', Rule::in(EquipoHistorico::TIPOS_SERVICIO)],
            'fecha_servicio' => ['required', 'date', 'before_or_equal:today'],
            'nombre_laboratorio' => ['required', 'string', 'max:255'],
            'es_tercero' => ['boolean'],
            'adjunto' => [
                'nullable',
                'file',
                'extensions:'.implode(',', EquipoHistorico::EXTENSIONES_ADJUNTO),
                'max:'.EquipoHistorico::TAMANIO_MAXIMO_ADJUNTO_KB,
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_servicio.in' => 'El tipo de servicio debe ser Mantenimiento o Calibración.',
            'fecha_servicio.before_or_equal' => 'La fecha del servicio no puede ser futura.',
            'adjunto.extensions' => 'El adjunto debe ser un archivo '.implode(', ', EquipoHistorico::EXTENSIONES_ADJUNTO).'.',
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
            'fecha_servicio' => 'fecha del servicio',
            'nombre_laboratorio' => 'laboratorio',
            'es_tercero' => 'es tercero',
            'adjunto' => 'adjunto',
        ];
    }
}
