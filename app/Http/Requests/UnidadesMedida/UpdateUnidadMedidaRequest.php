<?php

namespace App\Http\Requests\UnidadesMedida;

use App\Models\UnidadMedida;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUnidadMedidaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('unidadMedida'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var UnidadMedida $unidadMedida */
        $unidadMedida = $this->route('unidadMedida');

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('unidad_medidas', 'nombre')
                    ->where('tenant_id', $unidadMedida->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($unidadMedida->id),
            ],
            'simbolo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('unidad_medidas', 'simbolo')
                    ->where('tenant_id', $unidadMedida->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($unidadMedida->id),
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
            'nombre' => 'nombre',
            'simbolo' => 'símbolo',
        ];
    }
}
