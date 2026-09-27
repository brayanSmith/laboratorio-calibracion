<?php

namespace App\Http\Requests\ProcedimientosCalibracion;

use App\Models\ProcedimientoCalibracion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateProcedimientoCalibracionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('procedimientoCalibracion'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ProcedimientoCalibracion $procedimientoCalibracion */
        $procedimientoCalibracion = $this->route('procedimientoCalibracion');

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('procedimiento_calibracions', 'nombre')
                    ->where('tenant_id', $procedimientoCalibracion->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($procedimientoCalibracion->id),
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
            'nombre' => 'nombre',
        ];
    }
}
