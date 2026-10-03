<?php

namespace App\Http\Requests\OrdenesTrabajo;

use App\Enums\TenantPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrdenTrabajoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(TenantPermission::EquiposEditar->value);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // equipos_listos llega como {id: {...}}, indexado por el id de la programación
        // (así lo arma la modal, con un input hidden por campo). Se convierte a una
        // lista con el id explícito para poder validarlo con Rule::exists.
        /** @var array<string, array<string, mixed>> $equiposPorId */
        $equiposPorId = $this->input('equipos_listos', []);

        $equipos = collect($equiposPorId)
            ->map(function (array $equipo, string $id) {
                $listo = filter_var($equipo['listo_para_mantenimiento'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $tercero = filter_var($equipo['mantenimiento_asignado_tercero'] ?? false, FILTER_VALIDATE_BOOLEAN);

                return [
                    ...$equipo,
                    'id' => (int) $id,
                    'listo_para_mantenimiento' => $listo,
                    'mantenimiento_asignado_tercero' => $tercero,
                    // Solo exigen tecnico_id/novedad_id o empresa_tercero_id cuando el
                    // equipo realmente queda listo: un equipo sin marcar no debe bloquear
                    // el envío solo porque le falten esos campos.
                    'requiere_tecnico' => $listo && ! $tercero,
                    'requiere_tercero' => $listo && $tercero,
                ];
            })
            ->values()
            ->all();

        $this->merge(['equipos_listos' => $equipos]);
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
            'equipos_listos' => ['array'],
            'equipos_listos.*.id' => [
                'required',
                Rule::exists('equipo_programacions', 'id')->where('tenant_id', $tenantId)->where('ingresado', true),
            ],
            'equipos_listos.*.listo_para_mantenimiento' => ['boolean'],
            'equipos_listos.*.mantenimiento_asignado_tercero' => ['boolean'],

            // Si queda listo y NO se asigna a un tercero, lo hace un técnico propio: exige
            // el técnico. La novedad (catálogo de Novedad, categoría MANTENIMIENTO) es
            // opcional: novedad_id es nullable, se reporta solo si surge una.
            'equipos_listos.*.tecnico_id' => [
                'nullable',
                'required_if:equipos_listos.*.requiere_tecnico,true',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'equipos_listos.*.novedad_id' => [
                'nullable',
                Rule::exists('novedads', 'id')->where('tenant_id', $tenantId)->where('categoria', 'MANTENIMIENTO')->whereNull('deleted_at'),
            ],

            // Si queda listo Y asignado a un tercero, exige la empresa que lo presta.
            'equipos_listos.*.empresa_tercero_id' => [
                'nullable',
                'required_if:equipos_listos.*.requiere_tercero,true',
                Rule::exists('empresa_terceros', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
        ];
    }
}
