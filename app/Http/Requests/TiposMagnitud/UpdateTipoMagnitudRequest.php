<?php

namespace App\Http\Requests\TiposMagnitud;

use App\Models\TipoMagnitud;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTipoMagnitudRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('tipoMagnitud'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var TipoMagnitud $tipoMagnitud */
        $tipoMagnitud = $this->route('tipoMagnitud');

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tipo_magnituds', 'nombre')
                    ->where('tenant_id', $tipoMagnitud->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($tipoMagnitud->id),
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
        ];
    }
}
