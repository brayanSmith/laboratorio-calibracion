<?php

namespace App\Modules\Recordatorios\Http\Requests;

use App\Modules\Recordatorios\Models\RecordatorioPlantilla;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRecordatorioPlantillaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', RecordatorioPlantilla::class);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            $this->merge(['nombre' => trim($this->input('nombre'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', Rule::in(RecordatorioPlantilla::TIPOS)],
            'nombre' => [
                'required', 'string', 'max:80',
                Rule::unique('recordatorio_plantillas')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('tipo', $this->input('tipo')),
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
        return ['tipo' => 'tipo de correo', 'nombre' => 'nombre de la plantilla'];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['nombre.unique' => 'Ya hay una plantilla con ese nombre para este correo.'];
    }
}
