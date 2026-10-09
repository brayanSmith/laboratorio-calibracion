<?php

namespace App\Http\Requests\ServicioTerceros;

use App\Models\EmpresaTercero;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateServicioTerceroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('servicio_tercero'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'empresa_tercero_id' => [
                'required',
                'integer',
                Rule::exists(EmpresaTercero::class, 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'pdf_servicio' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
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
            'empresa_tercero_id' => 'empresa tercera',
            'pdf_servicio' => 'documento del servicio',
        ];
    }
}
