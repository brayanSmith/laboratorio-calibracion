<?php

namespace App\Modules\Importaciones\Http\Requests;

use App\Enums\TipoImportacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImportacionRequest extends FormRequest
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
     * Get the validation rules that apply to the request. Los campos del mapeo dependen del
     * tipo de importación.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = [
            'tipo' => ['required', Rule::enum(TipoImportacion::class)],
            // Nombre con el que se guardó el CSV al leer sus encabezados (ver ImportacionController::encabezados()).
            'archivo' => ['required', 'string', 'regex:/^[A-Za-z0-9]{40}\.csv$/'],
            'nombre_original' => ['required', 'string', 'max:255'],
        ];

        $importador = $this->tipo()?->importador();

        if ($importador === null) {
            return $reglas;
        }

        $reglas['mapeo'] = ['required', 'array:'.implode(',', $importador::claves())];
        $reglas['mapeo.*'] = ['nullable', 'integer', 'min:0'];

        foreach ($importador::requeridos() as $clave) {
            $reglas["mapeo.{$clave}"] = ['required', 'integer', 'min:0'];
        }

        return $reglas;
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $atributos = ['tipo' => 'qué importar'];
        $importador = $this->tipo()?->importador();

        foreach ($importador === null ? [] : $importador::campos() as $campo) {
            $atributos["mapeo.{$campo['clave']}"] = $campo['etiqueta'];
        }

        return $atributos;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mapeo.*.required' => 'Elige la columna del CSV para :attribute.',
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
