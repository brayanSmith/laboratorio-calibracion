<?php

namespace App\Modules\Equipos\Http\Requests;

use App\Enums\TenantPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Documentación común a todos los equipos de un mismo tipo, marca y modelo: los documentos
 * se suben una sola vez y quedan en cada equipo que coincida.
 */
class StoreDocumentacionModeloRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(TenantPermission::EquiposEditar->value);
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
            'tipo_equipo_id' => ['required', Rule::exists('tipo_equipos', 'id')->where('tenant_id', $tenantId)],
            'fabricante_id' => ['required', Rule::exists('fabricantes', 'id')->where('tenant_id', $tenantId)],
            'modelo' => ['required', 'string', 'max:255'],
            'documentos' => ['required', 'array', 'min:1'],
            'documentos.*.nombre' => ['required', 'string', 'max:255'],
            'documentos.*.archivo' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp',
                'max:10240',
            ],
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
            'tipo_equipo_id' => 'tipo de equipo',
            'fabricante_id' => 'marca',
            'modelo' => 'modelo',
            'documentos' => 'documentos',
            'documentos.*.nombre' => 'nombre del documento',
            'documentos.*.archivo' => 'archivo del documento',
        ];
    }

    /**
     * Get the custom messages for the validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documentos.required' => 'Agrega al menos un documento.',
        ];
    }
}
