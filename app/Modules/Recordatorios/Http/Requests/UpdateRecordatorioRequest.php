<?php

namespace App\Modules\Recordatorios\Http\Requests;

use App\Modules\Recordatorios\Models\Recordatorio;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateRecordatorioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', Recordatorio::class);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'activo' => $this->boolean('activo'),
            'enviar_a_clientes' => $this->boolean('enviar_a_clientes'),
            'enviar_resumen_bahia' => $this->boolean('enviar_resumen_bahia'),
            // Sin correos el formulario no envía el campo; se ignoran los vacíos y se
            // comparan en minúsculas para detectar repetidos.
            'correos_copia' => collect((array) $this->input('correos_copia', []))
                ->map(fn ($correo) => is_string($correo) ? mb_strtolower(trim($correo)) : $correo)
                ->filter(fn ($correo) => filled($correo))
                ->values()
                ->all(),
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
            'activo' => ['boolean'],
            'frecuencia' => ['required', 'string', Rule::in(Recordatorio::FRECUENCIAS)],
            'dia_semana' => ['nullable', 'required_if:frecuencia,SEMANAL', 'integer', 'between:1,7'],
            'dia_mes' => ['nullable', 'required_if:frecuencia,MENSUAL', 'integer', 'between:1,28'],
            'mes_aviso' => ['nullable', 'required_if:frecuencia,MENSUAL', 'string', Rule::in(Recordatorio::MESES_AVISO)],
            'hora_envio' => ['required', 'date_format:H:i'],
            // En el mensual el rango va hasta fin de mes; los días solo aplican al semanal.
            'dias_anticipacion' => ['nullable', 'required_if:frecuencia,SEMANAL', 'integer', 'between:1,365'],
            'enviar_a_clientes' => ['boolean'],
            'enviar_resumen_bahia' => ['boolean'],
            'correos_copia' => ['array', 'max:20'],
            'correos_copia.*' => ['string', 'email', 'max:255', 'distinct'],
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
            'activo' => 'activo',
            'frecuencia' => 'frecuencia',
            'dia_semana' => 'día de la semana',
            'dia_mes' => 'día del mes',
            'mes_aviso' => 'mes a avisar',
            'hora_envio' => 'hora de envío',
            'dias_anticipacion' => 'días de anticipación',
            'enviar_a_clientes' => 'enviar a clientes',
            'enviar_resumen_bahia' => 'resumen por bahía',
            'correos_copia' => 'con copia a',
            'correos_copia.*' => 'correo con copia',
        ];
    }

    /**
     * Los datos a guardar: solo el día que corresponde a la frecuencia elegida. Los textos
     * del correo se guardan aparte, desde la pestaña de contenido.
     *
     * @return array<string, mixed>
     */
    public function datosRecordatorio(): array
    {
        $datos = $this->validated();
        $mensual = $datos['frecuencia'] === 'MENSUAL';

        // Lo que no aplica a la frecuencia elegida y no llegó se deja como estaba guardado,
        // para cuando se vuelva a la otra frecuencia.
        foreach (['dias_anticipacion', 'mes_aviso'] as $campo) {
            if (blank($datos[$campo] ?? null)) {
                unset($datos[$campo]);
            }
        }

        return [
            ...$datos,
            'dia_semana' => $mensual ? null : (int) $datos['dia_semana'],
            'dia_mes' => $mensual ? (int) $datos['dia_mes'] : null,
        ];
    }
}
