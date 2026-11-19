<?php

namespace App\Modules\Catalogos\Http\Requests;

use App\Modules\Catalogos\Models\Bahia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AsignarClientesBahiasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('asignarClientes', Bahia::class);
    }

    /**
     * Cada bahía enviada queda con exactamente los clientes indicados (sin ninguno, si la
     * lista va vacía). Las bahías y los clientes deben ser del tenant.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'asignaciones' => ['present', 'array'],
            'asignaciones.*.bahia_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('bahias', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'asignaciones.*.cliente_ids' => ['present', 'array'],
            'asignaciones.*.cliente_ids.*' => [
                'integer',
                Rule::exists('clientes', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
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
            'asignaciones.*.bahia_id' => 'bahía',
            'asignaciones.*.cliente_ids.*' => 'cliente',
        ];
    }
}
