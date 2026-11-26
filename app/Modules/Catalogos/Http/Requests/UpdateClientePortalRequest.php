<?php

namespace App\Modules\Catalogos\Http\Requests;

use App\Modules\Catalogos\Models\Cliente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateClientePortalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('cliente'));
    }

    /**
     * El usuario se guarda en minúsculas y sin espacios: así se escribe igual al entrar.
     */
    protected function prepareForValidation(): void
    {
        $usuario = trim((string) $this->input('portal_usuario'));

        $this->merge([
            'portal_activo' => $this->boolean('portal_activo'),
            'enviar_correo' => $this->boolean('enviar_correo'),
            'portal_usuario' => $usuario === '' ? null : Str::lower($usuario),
            'contrasena' => $this->filled('contrasena') ? (string) $this->input('contrasena') : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Cliente $cliente */
        $cliente = $this->route('cliente');

        return [
            'portal_activo' => ['boolean'],
            // Único en toda la plataforma: al entrar, el usuario dice de qué laboratorio es.
            'portal_usuario' => [
                'nullable',
                'required_if:portal_activo,true',
                'string',
                'min:3',
                'max:100',
                'regex:/^[a-z0-9._@-]+$/',
                Rule::unique('clientes', 'portal_usuario')->ignore($cliente->id),
            ],
            'contrasena' => ['nullable', 'string', Password::min(8)],
            'enviar_correo' => ['boolean'],
        ];
    }

    /**
     * Para habilitar el acceso el cliente debe tener contraseña (la guardada o una nueva), y
     * para enviarla por correo hay que escribir una nueva (la guardada no se puede leer).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Cliente $cliente */
                $cliente = $this->route('cliente');
                $nueva = $this->input('contrasena');

                if ($this->boolean('portal_activo') && $nueva === null && $cliente->portal_password === null) {
                    $validator->errors()->add('contrasena', __('Asigna una contraseña para habilitar el acceso.'));
                }

                if ($this->boolean('enviar_correo') && $nueva === null) {
                    $validator->errors()->add('contrasena', __('Escribe o genera una contraseña nueva para enviarla por correo.'));
                }
            },
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
            'portal_usuario.required_if' => 'Escribe el usuario para habilitar el acceso.',
            'portal_usuario.regex' => 'El usuario solo puede tener letras, números y . _ - @ (sin espacios).',
            'portal_usuario.unique' => 'Ese usuario ya lo tiene otro cliente.',
        ];
    }
}
