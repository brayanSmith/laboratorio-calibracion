<?php

namespace App\Http\Requests\Usuarios;

use App\Concerns\ValidatesRoleAssignment;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreUsuarioRequest extends FormRequest
{
    use ValidatesRoleAssignment;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', User::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'password' => ['required', 'string', Password::default()],
        ];
    }

    /**
     * Get the validator instance callbacks that run after the rules.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateRoleAssignment($validator)];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'role_id' => 'rol',
            'password' => 'contraseña temporal',
        ];
    }
}
