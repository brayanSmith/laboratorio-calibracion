<?php

namespace App\Http\Requests\Equipos;

use App\Models\Equipo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreEquipoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Equipo::class);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'activo' => $this->boolean('activo'),
            'patron_referencia' => $this->boolean('patron_referencia'),
            'requiere_programacion' => $this->boolean('requiere_programacion'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'codigo' => ['required', 'string', 'max:255', Rule::unique('equipos', 'codigo')],
            'tipo_equipo_id' => ['required', Rule::exists('tipo_equipos', 'id')->where('tenant_id', $tenantId)],
            'tipo_tecnologia' => ['required', 'in:ANALOGICO,DIGITAL'],
            'modelo' => ['required', 'string', 'max:255'],
            'fabricante_id' => ['required', Rule::exists('fabricantes', 'id')->where('tenant_id', $tenantId)],
            'numero_serie' => ['required', 'string', 'max:255'],
            'area_id' => ['required', Rule::exists('areas', 'id')->where('tenant_id', $tenantId)],
            'bahia_id' => ['required', Rule::exists('bahias', 'id')->where('tenant_id', $tenantId)],
            'condicion_actual' => ['required', 'string', 'max:255'],
            'notas' => ['nullable', 'string'],
            'activo' => ['boolean'],
            'patron_referencia' => ['boolean'],
            'concatenar_codigo_nombre' => ['nullable', 'string', 'max:255'],
            'requiere_programacion' => ['boolean'],
            'cliente_id' => ['required', Rule::exists('clientes', 'id')->where('tenant_id', $tenantId)],

            // Especificación técnica: opcional, pero si se llena un campo se exigen todos.
            'tipo_magnitud_id' => ['nullable', Rule::exists('tipo_magnituds', 'id')->where('tenant_id', $tenantId)],
            'unidad_medida_id' => ['required_with:tipo_magnitud_id', Rule::exists('unidad_medidas', 'id')->where('tenant_id', $tenantId)],
            'alcance_indicacion' => ['required_with:tipo_magnitud_id', 'numeric'],
            'precision' => ['required_with:tipo_magnitud_id', 'numeric'],
            'resolucion' => ['required_with:tipo_magnitud_id', 'numeric'],

            // Programaciones de servicio: opcionales, cualquier cantidad (un equipo puede tener varias).
            'programaciones' => ['nullable', 'array'],
            'programaciones.*.tipo_servicio' => ['required', Rule::in(['MANTENIMIENTO', 'CALIBRACION'])],
            'programaciones.*.intervalo_servicio' => ['nullable', 'numeric'],
            'programaciones.*.fecha_apertura_historial_servicio' => ['nullable', 'date'],
            'programaciones.*.fecha_ultimo_servicio' => ['nullable', 'date'],
            'programaciones.*.fecha_proximo_servicio' => ['nullable', 'date'],
            'programaciones.*.dias_plazo_vencimiento' => ['required', 'numeric'],
            'programaciones.*.estado_vencimiento' => ['required', Rule::in(['AL_DIA', 'PROXIMO_A_VENCER', 'VENCIDO'])],

            // Documentos: opcionales, cualquier cantidad, cada uno con su propio nombre y archivo.
            'documentos' => ['nullable', 'array'],
            'documentos.*.nombre' => ['required_with:documentos.*.archivo', 'string', 'max:255'],
            'documentos.*.archivo' => [
                'required_with:documentos.*.nombre',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp',
                'max:10240',
            ],
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
            'programaciones.*.tipo_servicio' => 'tipo de servicio',
            'programaciones.*.intervalo_servicio' => 'intervalo de servicio',
            'programaciones.*.fecha_apertura_historial_servicio' => 'fecha de apertura del historial',
            'programaciones.*.fecha_ultimo_servicio' => 'fecha del último servicio',
            'programaciones.*.fecha_proximo_servicio' => 'fecha del próximo servicio',
            'programaciones.*.dias_plazo_vencimiento' => 'días de plazo de vencimiento',
            'programaciones.*.estado_vencimiento' => 'estado de vencimiento',
            'documentos.*.nombre' => 'nombre del documento',
            'documentos.*.archivo' => 'archivo del documento',
        ];
    }
}
