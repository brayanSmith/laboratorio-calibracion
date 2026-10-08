<?php

namespace App\Http\Requests\OrdenesTrabajo;

use App\Enums\TenantPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDespachoAgendamientoRequest extends FormRequest
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
        // despachos_listos llega como {id: {...}}, indexado por el id del despacho (así
        // lo arma la modal, con un input hidden por campo). Se convierte a una lista con
        // el id explícito para poder validarlo con Rule::exists.
        /** @var array<string, array<string, mixed>> $despachosPorId */
        $despachosPorId = $this->input('despachos_listos', []);

        $despachos = collect($despachosPorId)
            ->map(fn (array $despacho, string $id) => [
                ...$despacho,
                'id' => (int) $id,
                'entrega_autorizada' => filter_var($despacho['entrega_autorizada'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();

        $this->merge(['despachos_listos' => $despachos]);
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
            'despachos_listos' => ['array'],
            'despachos_listos.*.id' => [
                'required',
                Rule::exists('despachos', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('tecnico_entrega_id'),
            ],
            'despachos_listos.*.entrega_autorizada' => ['boolean'],

            // El técnico es opcional: un despacho sin técnico elegido simplemente se deja
            // sin tocar (ver OrdenTrabajoController::storeDespacho()). Pero si se autoriza
            // la entrega, exige saber quién la hace.
            'despachos_listos.*.tecnico_id' => [
                'nullable',
                'required_if:despachos_listos.*.entrega_autorizada,true',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
