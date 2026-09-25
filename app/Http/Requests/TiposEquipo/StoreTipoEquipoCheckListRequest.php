<?php

namespace App\Http\Requests\TiposEquipo;

use App\Models\TipoEquipo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTipoEquipoCheckListRequest extends FormRequest
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
            'nombres' => ['required', 'array', 'min:1', 'max:100'],
            'nombres.*' => [
                'required',
                'string',
                'max:255',
                'distinct:ignore_case',
                Rule::unique('tipo_equipo_check_lists', 'nombre')
                    ->where('tipo_equipo_id', $tipoEquipo->id)
                    ->whereNull('deleted_at'),
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
            'nombres' => 'ítems del checklist',
            'nombres.*' => 'ítem del checklist',
        ];
    }
}
