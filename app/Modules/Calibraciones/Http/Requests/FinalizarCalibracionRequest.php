<?php

namespace App\Modules\Calibraciones\Http\Requests;

use App\Modules\Calibraciones\Concerns\ReglasFormularioCalibracion;
use App\Modules\Calibraciones\Models\Calibracion;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FinalizarCalibracionRequest extends FormRequest
{
    use ReglasFormularioCalibracion;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('finalizar', $this->route('calibracion'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'firmado' => $this->boolean('firmado'),
            ...($this->incluyeFormulario() ? ['ajustes_requeridos' => $this->boolean('ajustes_requeridos')] : []),
        ]);
    }

    /**
     * "Finalizar" puede traer el formulario de edición completo (temperatura, humedad,
     * valores del instrumento...) para guardarlo en la misma petición. Si no viene, se
     * finaliza con lo que ya estaba guardado.
     */
    public function incluyeFormulario(): bool
    {
        return $this->hasAny(['temperatura', 'humedad', 'detalles', 'procedimiento_id', 'novedad_id']);
    }

    /**
     * Whether the formulario sent along must be saved: only who can edit the calibracion.
     */
    public function guardaFormulario(): bool
    {
        return $this->incluyeFormulario() && Gate::allows('update', $this->route('calibracion'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Calibracion $calibracion */
        $calibracion = $this->route('calibracion');

        return [
            'estado_calibracion' => ['required', Rule::in(['FINALIZADO', 'DEVOLVER_MANTENIMIENTO', 'FUERA_DE_SERVICIO'])],
            'firmado' => ['boolean'],
            ...($this->guardaFormulario() ? $this->reglasFormulario($calibracion, alFinalizar: true) : []),
        ];
    }

    /**
     * Para finalizar una calibración deben estar registradas la temperatura y la humedad, y
     * todos los resultados de la medición deben tener su valor del instrumento (el resultado
     * de la calibración se calcula a partir de ellos). Si la petición trae el formulario,
     * eso lo validan sus reglas; si no, se revisa lo que ya estaba guardado.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('estado_calibracion') !== 'FINALIZADO' || $this->guardaFormulario()) {
                return;
            }

            /** @var Calibracion $calibracion */
            $calibracion = $this->route('calibracion');

            foreach (['temperatura' => 'la temperatura', 'humedad' => 'la humedad'] as $campo => $nombre) {
                if ($calibracion->{$campo} === null) {
                    $validator->errors()->add($campo, __('Registra :campo y guarda los cambios antes de finalizar.', ['campo' => $nombre]));
                }
            }

            if ($calibracion->detalleMedicionCalibracion()->whereNull('resultado_calibracion')->exists()) {
                $validator->errors()->add('detalles', __('Completa el valor del instrumento en todos los resultados y guarda los cambios antes de finalizar.'));
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
            'estado_calibracion' => 'estado de la calibración',
            'firmado' => 'firmado',
            ...$this->atributosFormulario(),
        ];
    }
}
