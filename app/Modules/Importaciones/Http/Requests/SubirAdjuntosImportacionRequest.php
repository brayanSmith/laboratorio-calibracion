<?php

namespace App\Modules\Importaciones\Http\Requests;

use App\Enums\TipoImportacion;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubirAdjuntosImportacionRequest extends FormRequest
{
    /**
     * Archivos por petición: PHP acepta 20 por defecto (max_file_uploads), así que el
     * navegador sube la carpeta en tandas de este tamaño.
     */
    public const ARCHIVOS_POR_TANDA = 20;

    /** Extensiones aceptadas como adjunto; el navegador omite los demás archivos de la carpeta. */
    public const EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

    /** Tamaño máximo de cada archivo, en KB. */
    public const TAMANIO_MAXIMO_KB = 20480;

    /**
     * Determine if the user is authorized to make this request. Un tipo que no existe o que
     * no acepta adjuntos no se autoriza aquí: lo rechaza la validación.
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
            'tipo' => [
                'required',
                Rule::enum(TipoImportacion::class),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->tipo()?->importador()::aceptaAdjuntos() === false) {
                        $fail('Este tipo de importación no acepta adjuntos.');
                    }
                },
            ],
            // Lo genera el navegador al empezar a subir la carpeta; agrupa todas sus tandas.
            'lote' => ['required', 'string', 'regex:/^[A-Za-z0-9]{40}$/'],
            'adjuntos' => ['required', 'array', 'min:1', 'max:'.self::ARCHIVOS_POR_TANDA],
            'adjuntos.*' => ['file', 'extensions:'.implode(',', self::EXTENSIONES), 'max:'.self::TAMANIO_MAXIMO_KB],
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
            'adjuntos.*.extensions' => 'Hay un archivo que no es un tipo de adjunto permitido ('.implode(', ', self::EXTENSIONES).').',
            'adjuntos.*.max' => 'Cada adjunto debe pesar como máximo '.(self::TAMANIO_MAXIMO_KB / 1024).' MB.',
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
