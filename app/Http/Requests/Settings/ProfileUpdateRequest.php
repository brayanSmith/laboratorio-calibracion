<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'eliminar_firma' => $this->boolean('eliminar_firma'),
            'eliminar_foto' => $this->boolean('eliminar_foto'),
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
            ...$this->profileRules($this->user()->id),
            'firma' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'eliminar_firma' => ['boolean'],
            'foto' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'eliminar_foto' => ['boolean'],
        ];
    }
}
