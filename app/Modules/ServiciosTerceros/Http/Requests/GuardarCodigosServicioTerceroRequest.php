<?php

namespace App\Modules\ServiciosTerceros\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GuardarCodigosServicioTerceroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('finalizar', $this->route('servicio_tercero'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'codigo_compra' => ['nullable', 'string', 'max:50'],
            'codigo_despacho' => ['nullable', 'string', 'max:50'],
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
            'codigo_compra' => 'Solped',
            'codigo_despacho' => 'OB',
        ];
    }
}
