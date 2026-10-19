<?php

namespace App\Modules\Calibraciones\Http\Requests;

use App\Modules\Calibraciones\Concerns\ReglasFormularioCalibracion;
use App\Modules\Calibraciones\Models\Calibracion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateCalibracionRequest extends FormRequest
{
    use ReglasFormularioCalibracion;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('calibracion'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'ajustes_requeridos' => $this->boolean('ajustes_requeridos'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Calibracion $calibracion */
        $calibracion = $this->route('calibracion');

        return $this->reglasFormulario($calibracion, alFinalizar: false);
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->atributosFormulario();
    }
}
