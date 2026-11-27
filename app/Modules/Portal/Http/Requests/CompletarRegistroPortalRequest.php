<?php

namespace App\Modules\Portal\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * El cliente elige su usuario y su contraseña desde el enlace firmado que le llegó al correo.
 * La firma de la ruta ya prueba que el correo es suyo.
 */
class CompletarRegistroPortalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El usuario se guarda en minúsculas y sin espacios: así se escribe igual al entrar.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'portal_usuario' => Str::lower(trim((string) $this->input('portal_usuario'))),
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
            // Único en toda la plataforma, como cuando el laboratorio da el acceso.
            'portal_usuario' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[a-z0-9._@-]+$/', Rule::unique('clientes', 'portal_usuario')],
            'contrasena' => ['required', 'string', 'confirmed', Password::min(8)],
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
            'portal_usuario' => 'usuario',
            'contrasena' => 'contraseña',
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
            'portal_usuario.regex' => 'El usuario solo puede tener letras, números y . _ - @ (sin espacios).',
            'portal_usuario.unique' => 'Ese usuario ya está en uso, elige otro.',
        ];
    }
}
