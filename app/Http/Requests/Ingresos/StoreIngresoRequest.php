<?php

namespace App\Http\Requests\Ingresos;

use App\Enums\TenantPermission;
use App\Models\Ingreso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreIngresoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! Gate::allows('create', Ingreso::class)) {
            return false;
        }

        // Tocar los equipos (programaciones_actualizadas/programaciones_correctivas)
        // requiere permiso de editar equipos, además del de crear el ingreso: un
        // usuario con solo "ingresos.crear" (ej. Recepción) no puede tocar equipos.
        $tocaEquipos = filled($this->input('programaciones_actualizadas')) || filled($this->input('programaciones_correctivas'));

        return ! $tocaEquipos || $this->user()->can(TenantPermission::EquiposEditar->value);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // programaciones_actualizadas llega como {id: {...}}, indexado por el id de la
        // programación (así lo arma el formulario, con un input hidden por campo). Se
        // convierte a una lista con el id explícito para poder validarlo con Rule::exists.
        /** @var array<string, array<string, mixed>> $cambiosPorId */
        $cambiosPorId = $this->input('programaciones_actualizadas', []);

        $actualizadas = collect($cambiosPorId)
            ->map(fn (array $cambio, string $id) => [
                ...$cambio,
                'id' => (int) $id,
                're_agendar' => filter_var($cambio['re_agendar'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();

        $this->merge(['programaciones_actualizadas' => $actualizadas]);
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
            'bahia_id' => ['required', Rule::exists('bahias', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            // El técnico, el cliente, la firma y el estado se completan después, desde
            // "Recibir equipos" o "Cancelar ingreso" (ver UpdateEstadoIngresoRequest).

            // Equipos con servicio programado (preventivo) cuyo estado se tocó en el
            // buscador del formulario, o equipos correctivos agregados ahí mismo. Se
            // guardan junto con el ingreso, en la misma transacción, para poder
            // enlazarlos a su ingreso_id de una vez.
            'programaciones_actualizadas' => ['array'],
            'programaciones_actualizadas.*.id' => ['required', Rule::exists('equipo_programacions', 'id')->where('tenant_id', $tenantId)],
            'programaciones_actualizadas.*.estado_programacion' => ['required', Rule::in(['PENDIENTE', 'AGENDADO', 'CANCELADO'])],
            'programaciones_actualizadas.*.motivo_no_ingreso' => [
                'nullable',
                'required_if:programaciones_actualizadas.*.estado_programacion,CANCELADO',
                Rule::in(['USUARIO_NO_UBICADO', 'SUPERVISOR_AUTORIZA', 'EQUIPO_NO_UBICADO', 'OTRO']),
            ],
            'programaciones_actualizadas.*.observacion_no_ingreso' => ['nullable', 'required_if:programaciones_actualizadas.*.motivo_no_ingreso,OTRO', 'string', 'max:2000'],
            'programaciones_actualizadas.*.re_agendar' => ['boolean'],
            'programaciones_actualizadas.*.datos_re_agendamiento.fecha_proximo_agendamiento' => ['nullable', 'required_if:programaciones_actualizadas.*.re_agendar,true', 'date'],

            'programaciones_correctivas' => ['array'],
            'programaciones_correctivas.*.equipo_id' => [
                'required',
                Rule::exists('equipos', 'id')->where('tenant_id', $tenantId)->where('bahia_id', $this->input('bahia_id'))->whereNull('deleted_at'),
            ],
            'programaciones_correctivas.*.falla_detectada' => ['required', 'string', 'max:2000'],
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
            'bahia_id' => 'bahía',
            'desde' => 'fecha desde',
            'hasta' => 'fecha hasta',
        ];
    }
}
