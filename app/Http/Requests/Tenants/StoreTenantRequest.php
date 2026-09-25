<?php

namespace App\Http\Requests\Tenants;

use App\Concerns\ProfileValidationRules;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreTenantRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Tenant::class);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $slug = $this->filled('slug') ? $this->string('slug') : $this->string('nombre');

        $this->merge([
            'slug' => Str::slug($slug),
            'activo' => $this->boolean('activo'),
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
            'nombre' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tenants', 'slug')],
            'activo' => ['boolean'],
            'admin_name' => $this->nameRules(),
            'admin_email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'admin_password' => ['required', 'string', Password::default()],
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
            'admin_name' => 'nombre del administrador',
            'admin_email' => 'correo del administrador',
            'admin_password' => 'contraseña temporal',
        ];
    }
}
