<?php

namespace App\Http\Requests\Items;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreEntradaItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('item'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'recibido' => $this->boolean('recibido'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cantidad' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'precio_unitario' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'codigo_compra' => ['nullable', 'string', 'max:255'],
            'recibido' => ['boolean'],
            'fecha_recibido' => ['nullable', 'date', 'required_if:recibido,true'],
            'observaciones' => ['nullable', 'string', 'max:65535'],
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
            'cantidad' => 'cantidad',
            'precio_unitario' => 'precio unitario',
            'codigo_compra' => 'código de compra',
            'recibido' => 'recibido',
            'fecha_recibido' => 'fecha de recibido',
            'observaciones' => 'observaciones',
        ];
    }
}
