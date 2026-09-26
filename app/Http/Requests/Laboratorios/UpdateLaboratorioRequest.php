<?php

namespace App\Http\Requests\Laboratorios;

use App\Models\Laboratorio;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateLaboratorioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('laboratorio'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Laboratorio $laboratorio */
        $laboratorio = $this->route('laboratorio');

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('laboratorios', 'nombre')
                    ->where('tenant_id', $laboratorio->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($laboratorio->id),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
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
            'descripcion' => 'descripción',
            'direccion' => 'dirección',
        ];
    }
}
