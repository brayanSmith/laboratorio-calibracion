<?php

namespace App\Http\Requests\Empresa;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateEmpresaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $empresa = $this->user()->tenant?->empresa;

        abort_if($empresa === null, 404);

        return Gate::allows('update', $empresa);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['eliminar_logo' => $this->boolean('eliminar_logo')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nit' => ['required', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['required', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'eliminar_logo' => ['boolean'],
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
            'nit' => 'NIT',
            'nombre' => 'nombre',
            'direccion' => 'dirección',
            'telefono' => 'teléfono',
            'logo' => 'logo',
        ];
    }
}
