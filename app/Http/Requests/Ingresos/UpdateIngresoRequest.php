<?php

namespace App\Http\Requests\Ingresos;

use App\Enums\TenantPermission;
use App\Models\Ingreso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateIngresoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! Gate::allows('update', $this->route('ingreso'))) {
            return false;
        }

        // Tocar los equipos (programaciones_actualizadas/programaciones_correctivas)
        // requiere permiso de editar equipos, además del de editar el ingreso.
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
                'agendar' => filter_var($cambio['agendar'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();

        $this->merge(['programaciones_actualizadas' => $actualizadas]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Este formulario solo cubre bahía, fechas y equipos: el técnico, el cliente, la
     * firma, el estado y los motivos se manejan aparte, desde "Recibir equipos" o
     * "Cancelar ingreso" (ver UpdateEstadoIngresoRequest).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Ingreso $ingreso */
        $ingreso = $this->route('ingreso');
        $tenantId = $ingreso->tenant_id;

        return [
            'bahia_id' => ['required', Rule::exists('bahias', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],

            // Equipos con servicio programado (preventivo) encontrados por el buscador
            // del formulario, marcados con agendar = true/false, o equipos correctivos
            // agregados ahí mismo. Se guardan junto con el ingreso, en la misma
            // transacción, para poder enlazarlos a su ingreso_id de una vez.
            'programaciones_actualizadas' => ['array'],
            'programaciones_actualizadas.*.id' => ['required', Rule::exists('equipo_programacions', 'id')->where('tenant_id', $tenantId)],
            'programaciones_actualizadas.*.agendar' => ['boolean'],

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
