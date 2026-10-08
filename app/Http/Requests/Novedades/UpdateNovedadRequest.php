<?php

namespace App\Http\Requests\Novedades;

use App\Models\Novedad;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateNovedadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('novedad'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Novedad $novedad */
        $novedad = $this->route('novedad');

        return [
            'categoria' => ['required', 'string', Rule::in(Novedad::CATEGORIAS)],
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('novedads', 'nombre')
                    ->where('tenant_id', $novedad->tenant_id)
                    ->where('categoria', $this->input('categoria'))
                    ->whereNull('deleted_at')
                    ->ignore($novedad->id),
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
            'categoria' => 'categoría',
            'nombre' => 'nombre',
        ];
    }
}
