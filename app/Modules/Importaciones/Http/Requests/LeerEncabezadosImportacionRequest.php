<?php

namespace App\Modules\Importaciones\Http\Requests;

use App\Enums\TipoImportacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeerEncabezadosImportacionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Un tipo que no existe no se
     * autoriza aquí: lo rechaza la validación.
     */
    public function authorize(): bool
    {
        return $this->tipo()?->permitidoPara($this->user()) ?? true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoImportacion::class)],
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
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
            'tipo' => 'qué importar',
            'archivo' => 'archivo CSV',
        ];
    }

    /**
     * Tipo de importación elegido, o null si no es válido.
     */
    public function tipo(): ?TipoImportacion
    {
        return TipoImportacion::tryFrom((string) $this->input('tipo'));
    }
}
