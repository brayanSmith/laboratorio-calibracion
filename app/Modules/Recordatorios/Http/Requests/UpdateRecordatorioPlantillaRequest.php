<?php

namespace App\Modules\Recordatorios\Http\Requests;

use App\Modules\Recordatorios\Actions\LimpiarHtmlCorreo;
use App\Modules\Recordatorios\Models\RecordatorioPlantilla;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateRecordatorioPlantillaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->plantilla());
    }

    /**
     * Prepare the data for validation: nombre y asunto sin espacios sobrantes, y el mensaje
     * (HTML del editor) limpio de todo lo que no sea formato seguro, o null si quedó vacío.
     */
    protected function prepareForValidation(): void
    {
        $limpiar = app(LimpiarHtmlCorreo::class);
        $mensaje = $this->input('mensaje');

        if (is_string($mensaje)) {
            $mensaje = $limpiar->handle($mensaje);
            $mensaje = $limpiar->estaVacio($mensaje) ? null : $mensaje;
        }

        $this->merge([
            'nombre' => is_string($this->input('nombre')) ? trim($this->input('nombre')) : $this->input('nombre'),
            'asunto' => is_string($this->input('asunto')) ? trim($this->input('asunto')) : $this->input('asunto'),
            'mensaje' => $mensaje,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $plantilla = $this->plantilla();

        return [
            'nombre' => [
                'required', 'string', 'max:80',
                Rule::unique('recordatorio_plantillas')
                    ->where('tenant_id', $plantilla->tenant_id)
                    ->where('tipo', $plantilla->tipo)
                    ->ignore($plantilla->id),
            ],
            'asunto' => ['required', 'string', 'max:150'],
            'mensaje' => ['required', 'string', 'max:20000'],
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nombre' => 'nombre de la plantilla', 'asunto' => 'asunto', 'mensaje' => 'mensaje'];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['nombre.unique' => 'Ya hay una plantilla con ese nombre para este correo.'];
    }

    /**
     * The plantilla being edited.
     */
    private function plantilla(): RecordatorioPlantilla
    {
        return $this->route('plantilla');
    }
}
