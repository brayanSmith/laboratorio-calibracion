<?php

namespace App\Http\Requests\Mantenimientos;

use App\Models\Mantenimiento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMantenimientoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('mantenimiento'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        /** @var array<int, array<string, mixed>> $rawChecklist */
        $rawChecklist = $this->input('checklist', []);

        $checklist = array_map(fn (array $item) => [
            'id' => $item['id'] ?? null,
            'cumple' => filter_var($item['cumple'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'observacion' => $item['observacion'] ?? null,
        ], $rawChecklist);

        $this->merge([
            'firmado' => $this->boolean('firmado'),
            'checklist' => $checklist,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * tipo_mantenimiento no se valida aquí: se deriva de la programación que originó la
     * orden de trabajo y no se edita desde este formulario (ver OrdenTrabajoController).
     *
     * La modal de gestión guarda todo (datos del mantenimiento, checklist y, si se
     * llenaron, nuevos defectos/ítems usados/comentarios/fotos) con una sola solicitud.
     * Cada fila nueva es opcional: una fila vacía simplemente no crea nada (ver
     * MantenimientoController::update()).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Mantenimiento $mantenimiento */
        $mantenimiento = $this->route('mantenimiento');
        $tenantId = $mantenimiento->tenant_id;

        return [
            'fecha_mantenimiento' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'estado_inicial_equipo' => ['nullable', Rule::in(['OPERATIVO', 'FUERA_DE_SERVICIO'])],
            'estado_final_equipo' => ['nullable', Rule::in(['OPERATIVO', 'FUERA_DE_SERVICIO'])],
            'estado_mantenimiento' => ['required', Rule::in(['PENDIENTE', 'EN_PROCESO', 'FALTA_REPUESTOS', 'FINALIZADO'])],
            'tecnico_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'firmado' => ['boolean'],
            'novedad_id' => [
                'nullable',
                Rule::exists('novedads', 'id')->where('tenant_id', $tenantId)->where('categoria', 'MANTENIMIENTO')->whereNull('deleted_at'),
            ],

            'checklist' => ['array'],
            'checklist.*.id' => [
                'required',
                Rule::exists('mantenimiento_check_lists', 'id')->where('mantenimiento_id', $mantenimiento->id),
            ],
            'checklist.*.cumple' => ['boolean'],
            'checklist.*.observacion' => ['nullable', 'string', 'max:2000'],

            'nuevos_defectos' => ['array'],
            'nuevos_defectos.*.defecto_identificado' => ['nullable', 'string', 'max:255'],
            'nuevos_defectos.*.nivel_riesgo' => ['required_with:nuevos_defectos.*.defecto_identificado', Rule::in(['ALTO', 'MEDIO', 'BAJO'])],
            'nuevos_defectos.*.accion_correctiva' => ['nullable', 'string', 'max:2000'],

            'nuevos_items_usados' => ['array'],
            'nuevos_items_usados.*.item_id' => [
                'nullable',
                Rule::exists('items', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'nuevos_items_usados.*.cantidad' => ['nullable', 'required_with:nuevos_items_usados.*.item_id', 'numeric', 'min:0.01'],

            'nuevos_comentarios' => ['array'],
            'nuevos_comentarios.*' => ['nullable', 'string', 'max:2000'],

            'nuevas_fotos' => ['array'],
            'nuevas_fotos.*.archivo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'nuevas_fotos.*.descripcion' => ['nullable', 'string', 'max:255'],
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
            'fecha_mantenimiento' => 'fecha del mantenimiento',
            'descripcion' => 'descripción',
            'estado_inicial_equipo' => 'estado inicial del equipo',
            'estado_final_equipo' => 'estado final del equipo',
            'estado_mantenimiento' => 'estado del mantenimiento',
            'tecnico_id' => 'técnico',
            'novedad_id' => 'novedad',
            'nuevos_defectos.*.defecto_identificado' => 'defecto identificado',
            'nuevos_defectos.*.nivel_riesgo' => 'nivel de riesgo',
            'nuevos_defectos.*.accion_correctiva' => 'acción correctiva',
            'nuevos_items_usados.*.item_id' => 'ítem',
            'nuevos_items_usados.*.cantidad' => 'cantidad',
            'nuevos_comentarios.*' => 'comentario',
            'nuevas_fotos.*.archivo' => 'foto',
            'nuevas_fotos.*.descripcion' => 'descripción de la foto',
        ];
    }
}
