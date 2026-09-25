<?php

namespace App\Http\Requests\TiposEquipo;

use App\Models\TipoEquipoCheckList;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTipoEquipoCheckListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('tipoEquipoCheckList'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var TipoEquipoCheckList $item */
        $item = $this->route('tipoEquipoCheckList');

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tipo_equipo_check_lists', 'nombre')
                    ->where('tipo_equipo_id', $item->tipo_equipo_id)
                    ->whereNull('deleted_at')
                    ->ignore($item->id),
            ],
        ];
    }
}
