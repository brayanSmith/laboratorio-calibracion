<?php

namespace App\Http\Requests\Fabricantes;

use App\Models\Fabricante;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateFabricanteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('fabricante'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Fabricante $fabricante */
        $fabricante = $this->route('fabricante');

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('fabricantes', 'nombre')
                    ->where('tenant_id', $fabricante->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($fabricante->id),
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
