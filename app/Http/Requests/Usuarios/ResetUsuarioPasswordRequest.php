<?php

namespace App\Http\Requests\Usuarios;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class ResetUsuarioPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('resetPassword', $this->route('usuario'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|Password|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', Password::default()],
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
            'password' => 'contraseña temporal',
        ];
    }
}
