<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('tenant'));
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
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
            'activo' => ['boolean'],
        ];
    }
}
