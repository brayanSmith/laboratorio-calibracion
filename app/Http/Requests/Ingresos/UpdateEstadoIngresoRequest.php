<?php

namespace App\Http\Requests\Ingresos;

use App\Enums\TenantPermission;
use App\Models\Ingreso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateEstadoIngresoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! Gate::allows('update', $this->route('ingreso'))) {
            return false;
        }

        // Tocar los equipos (equipos_recibidos/equipos_correctivos_recibidos) requiere
        // permiso de editar equipos, además del de editar el ingreso.
        $tocaEquipos = filled($this->input('equipos_recibidos')) || filled($this->input('equipos_correctivos_recibidos'));

        return ! $tocaEquipos || $this->user()->can(TenantPermission::EquiposEditar->value);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['eliminar_firma' => $this->boolean('eliminar_firma')]);

        // equipos_recibidos llega como {id: {...}}, indexado por el id de la
        // programación (así lo arma el formulario, con un input hidden por campo). Se
        // convierte a una lista con el id explícito para poder validarlo con Rule::exists.
        /** @var array<string, array<string, mixed>> $recibidosPorId */
        $recibidosPorId = $this->input('equipos_recibidos', []);

        $recibidos = collect($recibidosPorId)
            ->map(fn (array $recibido, string $id) => [
                ...$recibido,
                'id' => (int) $id,
                'ingresado' => filter_var($recibido['ingresado'] ?? true, FILTER_VALIDATE_BOOLEAN),
                're_agendar' => filter_var($recibido['re_agendar'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();

        $this->merge(['equipos_recibidos' => $recibidos]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Usado tanto por "Recibir equipos" (estado_ingreso = RECIBIDO) como por
     * "Cancelar ingreso" (estado_ingreso = CANCELADO), y también para volver un
     * ingreso a PENDIENTE.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Ingreso $ingreso */
        $ingreso = $this->route('ingreso');
        $tenantId = $ingreso->tenant_id;

        return [
            'estado_ingreso' => ['required', Rule::in(['PENDIENTE', 'RECIBIDO', 'CANCELADO'])],
            'tecnico_recibe_id' => ['nullable', 'required_if:estado_ingreso,RECIBIDO', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'cliente_entrega_id' => ['nullable', 'required_if:estado_ingreso,RECIBIDO', Rule::exists('clientes', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'firma_cliente_entrega' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'eliminar_firma' => ['boolean'],
            'novedad' => ['nullable', 'string', 'max:5000'],
            'motivo_cancelacion' => ['nullable', 'required_if:estado_ingreso,CANCELADO', 'string', 'max:5000'],

            // Equipos programados (preventivos) ya enlazados a este ingreso, marcados
            // con ingresado = true/false al recibirlo. Si ingresado es false, se trata
            // como un cancelado: exige la novedad y permite registrar el re-agendamiento.
            'equipos_recibidos' => ['array'],
            'equipos_recibidos.*.id' => [
                'required',
                Rule::exists('equipo_programacions', 'id')->where('tenant_id', $tenantId)->where('ingreso_id', $ingreso->id),
            ],
            'equipos_recibidos.*.ingresado' => ['boolean'],
            'equipos_recibidos.*.novedad_ingreso_id' => [
                'nullable',
                'required_if:equipos_recibidos.*.ingresado,false',
                Rule::exists('novedads', 'id')->where('tenant_id', $tenantId)->where('categoria', 'INGRESO')->whereNull('deleted_at'),
            ],
            'equipos_recibidos.*.observacion_no_ingreso' => ['nullable', 'string', 'max:2000'],
            'equipos_recibidos.*.re_agendar' => ['boolean'],
            'equipos_recibidos.*.datos_re_agendamiento.fecha_proximo_agendamiento' => ['nullable', 'required_if:equipos_recibidos.*.re_agendar,true', 'date'],

            // Equipos sin servicio programado que se detectan de último momento, al
            // recibir el ingreso (ver AgregarEquipoCorrectivo).
            'equipos_correctivos_recibidos' => ['array'],
            'equipos_correctivos_recibidos.*.equipo_id' => [
                'required',
                Rule::exists('equipos', 'id')->where('tenant_id', $tenantId)->where('bahia_id', $ingreso->bahia_id)->whereNull('deleted_at'),
            ],
            'equipos_correctivos_recibidos.*.falla_detectada' => ['required', 'string', 'max:2000'],
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
            'estado_ingreso' => 'estado del ingreso',
            'tecnico_recibe_id' => 'técnico que recibe',
            'cliente_entrega_id' => 'cliente que entrega',
            'firma_cliente_entrega' => 'firma del cliente',
            'novedad' => 'novedad',
            'motivo_cancelacion' => 'motivo de cancelación',
        ];
    }
}
