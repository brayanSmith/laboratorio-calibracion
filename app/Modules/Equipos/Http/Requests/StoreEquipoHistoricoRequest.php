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
     * Prepare the data for validation. La empresa tercera solo se guarda si lo hizo un tercero.
     */
    protected function prepareForValidation(): void
    {
        $esTercero = $this->boolean('es_tercero');

        $this->merge([
            'es_tercero' => $esTercero,
            'empresa_tercero_id' => $esTercero ? $this->input('empresa_tercero_id') : null,
        ]);
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
            'es_tercero' => ['boolean'],
            'empresa_tercero_id' => [
                'nullable',
                'required_if:es_tercero,true',
                'integer',
                Rule::exists('empresa_terceros', 'id')
                    ->where('tenant_id', $this->route('equipo')->tenant_id)
                    ->withoutTrashed(),
            ],
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
            'empresa_tercero_id.required_if' => 'Selecciona la empresa tercera que hizo el servicio.',
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
            'es_tercero' => 'es tercero',
            'empresa_tercero_id' => 'empresa tercera',
            'adjunto' => 'adjunto',
        ];
    }
}
