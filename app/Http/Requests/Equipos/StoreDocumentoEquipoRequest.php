<?php

namespace App\Http\Requests\Equipos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreDocumentoEquipoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('equipo'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'archivo' => [
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
            'nombre' => 'nombre',
            'archivo' => 'archivo',
        ];
    }
}
