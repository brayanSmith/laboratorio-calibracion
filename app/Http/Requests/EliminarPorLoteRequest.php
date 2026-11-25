<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Registros elegidos en una tabla para eliminarlos de una vez (ver EliminaPorLote). El
 * permiso de eliminar por lote de cada módulo lo revisa el controlador.
 */
class EliminarPorLoteRequest extends FormRequest
{
    /** Máximo de registros por lote: una tabla entera, pero acotado para no colgar la petición. */
    public const MAXIMO_REGISTROS = 1000;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAXIMO_REGISTROS],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Selecciona al menos un registro.',
            'ids.max' => 'Se pueden eliminar hasta :max registros a la vez.',
        ];
    }
}
