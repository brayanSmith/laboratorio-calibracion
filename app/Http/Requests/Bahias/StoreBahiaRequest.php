<?php

namespace App\Http\Requests\Bahias;

use App\Models\Bahia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreBahiaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Bahia::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'area_id' => ['required', Rule::exists('areas', 'id')->where('tenant_id', $tenantId)],
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('bahias', 'nombre')
                    ->where('area_id', $this->input('area_id'))
                    ->whereNull('deleted_at'),
            ],
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
            'area_id' => 'área',
            'nombre' => 'nombre',
        ];
    }
}
