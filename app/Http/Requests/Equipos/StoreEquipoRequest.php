<?php

namespace App\Http\Requests\Equipos;

use App\Models\Equipo;
use App\Models\UnidadMedida;
use Closure;
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

            // Ficha técnica: se guarda en la columna JSON ficha_tecnica del equipo.
            'pais_procedencia' => ['required', 'string', 'max:255'],
            'numero_activo' => ['required', 'string', 'max:255'],
            'proveedor' => ['required', 'string', 'max:255'],
            'costo_usd' => ['required', 'numeric', 'min:0'],
            'fecha_adquisicion' => ['required', 'date'],

            // Especificación técnica: opcional, pero si se llena un campo se exigen todos.
            'tipo_magnitud_id' => ['nullable', Rule::exists('tipo_magnituds', 'id')->where('tenant_id', $tenantId)],
            'unidad_medida_id' => ['required_with:tipo_magnitud_id', Rule::exists('unidad_medidas', 'id')->where('tenant_id', $tenantId)],
            'alcance_indicacion' => ['required_with:tipo_magnitud_id', 'string', 'max:50', $this->alcanceIndicacionConSimbolo()],
            'precision' => ['required_with:tipo_magnitud_id', 'string', 'max:20', 'regex:/^(±\d+(\.\d{1,2})?|\d+(\.\d{1,2})?%)$/u'],
            'resolucion' => ['required_with:tipo_magnitud_id', 'string', 'max:50', $this->resolucionConSimbolo()],

            // Programaciones de servicio: opcionales, cualquier cantidad (un equipo puede tener varias).
            'programaciones' => ['nullable', 'array'],
            'programaciones.*.tipo_servicio' => ['required', 'array', 'min:1'],
            'programaciones.*.tipo_servicio.*' => [Rule::in(['MANTENIMIENTO', 'CALIBRACION'])],
            'programaciones.*.intervalo_servicio' => ['nullable', 'numeric'],
            'programaciones.*.intervalo_unidad' => ['required_with:programaciones.*.intervalo_servicio', Rule::in(['DIAS', 'SEMANAS', 'MESES'])],
            'programaciones.*.fecha_ultimo_servicio' => ['nullable', 'date'],

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
            'pais_procedencia' => 'país de procedencia',
            'numero_activo' => 'número de activo',
            'proveedor' => 'proveedor',
            'costo_usd' => 'costo en USD',
            'fecha_adquisicion' => 'fecha de adquisición',
            'tipo_magnitud_id' => 'tipo de magnitud',
            'unidad_medida_id' => 'unidad de medida',
            'alcance_indicacion' => 'alcance de indicación',
            'precision' => 'precisión',
            'resolucion' => 'resolución',
            'programaciones.*.tipo_servicio' => 'tipo de servicio',
            'programaciones.*.intervalo_servicio' => 'intervalo de servicio',
            'programaciones.*.intervalo_unidad' => 'unidad del intervalo',
            'programaciones.*.fecha_ultimo_servicio' => 'fecha del último servicio',
            'documentos.*.nombre' => 'nombre del documento',
            'documentos.*.archivo' => 'archivo del documento',
        ];
    }

    /**
     * Validate that alcance_indicacion ends with the símbolo of the selected unidad de medida
     * (e.g. "0 a 100mm"). Unlike precisión/resolución, admite texto libre antes del símbolo.
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

            if (! preg_match("/^.+{$simbolo}$/u", (string) $value)) {
                $fail("El alcance de indicación debe terminar con el símbolo de la unidad de medida seleccionada ({$unidad->simbolo}).");
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
}
