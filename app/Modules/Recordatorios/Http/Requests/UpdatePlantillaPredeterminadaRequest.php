<?php

namespace App\Modules\Recordatorios\Http\Requests;

use App\Modules\Recordatorios\Actions\LimpiarHtmlCorreo;
use App\Modules\Recordatorios\Models\Recordatorio;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePlantillaPredeterminadaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', Recordatorio::class);
    }

    /**
     * Prepare the data for validation: el asunto sin espacios sobrantes y el mensaje (HTML
     * del editor) limpio de todo lo que no sea formato seguro, o null si quedó sin contenido.
     */
    protected function prepareForValidation(): void
    {
        $limpiar = app(LimpiarHtmlCorreo::class);
        $asunto = $this->input('asunto');
        $mensaje = $this->input('mensaje');

        if (is_string($mensaje)) {
            $mensaje = $limpiar->handle($mensaje);
            $mensaje = $limpiar->estaVacio($mensaje) ? null : $mensaje;
        }

        $this->merge([
            'asunto' => is_string($asunto) ? trim($asunto) : $asunto,
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
        return [
            'asunto' => ['nullable', 'string', 'max:150'],
            'mensaje' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['asunto' => 'asunto', 'mensaje' => 'mensaje'];
    }

    /**
     * Los textos a guardar en el recordatorio, en null si quedaron vacíos o iguales a los
     * de por defecto, para que sigan los de por defecto si estos cambian.
     *
     * @param  'cliente'|'bahia'  $tipo
     * @return array<string, string|null>
     */
    public function datosPlantilla(string $tipo): array
    {
        $porDefecto = Recordatorio::PLANTILLAS_POR_DEFECTO[$tipo];
        // Se compara limpio igual que lo enviado, por si el editor cambia algún detalle.
        $mensajePorDefecto = app(LimpiarHtmlCorreo::class)->handle($porDefecto['mensaje']);
        $asunto = $this->validated('asunto');
        $mensaje = $this->validated('mensaje');

        return [
            "asunto_{$tipo}" => blank($asunto) || $asunto === $porDefecto['asunto'] ? null : $asunto,
            "mensaje_{$tipo}" => blank($mensaje) || $mensaje === $mensajePorDefecto ? null : $mensaje,
        ];
    }
}
