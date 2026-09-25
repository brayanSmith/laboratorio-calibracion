<?php

namespace App\Http\Requests\TiposEquipo;

use App\Models\TipoEquipo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTipoEquipoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('tipoEquipo'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var TipoEquipo $tipoEquipo */
        $tipoEquipo = $this->route('tipoEquipo');

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tipo_equipos', 'nombre')
                    ->where('tenant_id', $tipoEquipo->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($tipoEquipo->id),
            ],
            'tipo_mantenimiento' => ['required', Rule::in(['A', 'B'])],
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
            'tipo_mantenimiento' => 'tipo de mantenimiento',
        ];
    }
}
