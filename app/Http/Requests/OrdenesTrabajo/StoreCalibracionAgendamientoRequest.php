<?php

namespace App\Http\Requests\OrdenesTrabajo;

use App\Enums\TenantPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCalibracionAgendamientoRequest extends FormRequest
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
        // ordenes_listas llega como {id: {...}}, indexado por el id de la orden de
        // trabajo (así lo arma la modal, con un input hidden por campo). Se convierte a
        // una lista con el id explícito para poder validarlo con Rule::exists.
        /** @var array<string, array<string, mixed>> $ordenesPorId */
        $ordenesPorId = $this->input('ordenes_listas', []);

        $ordenes = collect($ordenesPorId)
            ->map(function (array $orden, string $id) {
                $listo = filter_var($orden['listo_para_calibracion'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $tercero = filter_var($orden['calibracion_asignado_tercero'] ?? false, FILTER_VALIDATE_BOOLEAN);

                return [
                    ...$orden,
                    'id' => (int) $id,
                    'listo_para_calibracion' => $listo,
                    'calibracion_asignado_tercero' => $tercero,
                    // Solo exigen tecnico_id o empresa_tercero_id cuando la orden
                    // realmente queda lista: una orden sin marcar no debe bloquear el
                    // envío solo porque le falten esos campos.
                    'requiere_tecnico' => $listo && ! $tercero,
                    'requiere_tercero' => $listo && $tercero,
                ];
            })
            ->values()
            ->all();

        $this->merge(['ordenes_listas' => $ordenes]);
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
            'ordenes_listas' => ['array'],
            'ordenes_listas.*.id' => [
                'required',
                // Ojo: Rule::exists()->where() con un booleano `false` no filtra correctamente
                // (compara mal contra la columna tinyint); hay que pasar 0/1 en su lugar.
                Rule::exists('orden_trabajos', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('mantenimiento_finalizado', 1)
                    ->where('calibracion_finalizado', 0)
                    ->where('listo_para_calibracion', 0),
            ],
            'ordenes_listas.*.listo_para_calibracion' => ['boolean'],
            'ordenes_listas.*.calibracion_asignado_tercero' => ['boolean'],

            // Si queda lista y NO se asigna a un tercero, la hace un técnico propio:
            // exige el técnico. La novedad (catálogo de Novedad, categoría CALIBRACION)
            // es opcional: novedad_id es nullable, se reporta solo si surge una.
            'ordenes_listas.*.tecnico_id' => [
                'nullable',
                'required_if:ordenes_listas.*.requiere_tecnico,true',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'ordenes_listas.*.novedad_id' => [
                'nullable',
                Rule::exists('novedads', 'id')->where('tenant_id', $tenantId)->where('categoria', 'CALIBRACION')->whereNull('deleted_at'),
            ],

            // Si queda lista Y asignada a un tercero, exige la empresa que la presta.
            'ordenes_listas.*.empresa_tercero_id' => [
                'nullable',
                'required_if:ordenes_listas.*.requiere_tercero,true',
                Rule::exists('empresa_terceros', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
        ];
    }
}
