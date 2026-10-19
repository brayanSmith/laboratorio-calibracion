<?php

namespace App\Http\Requests\ServicioTerceros;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FinalizarServicioTerceroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('finalizar', $this->route('servicio_tercero'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['re_agendar' => $this->boolean('re_agendar')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'estado_final_equipo' => ['required', Rule::in(['APROBADO', 'RECHAZADO'])],
            're_agendar' => ['boolean'],
            'pdf_servicio' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'codigo_compra' => ['nullable', 'string', 'max:50'],
            'codigo_despacho' => ['nullable', 'string', 'max:50'],
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
            'estado_final_equipo' => 'estado final del equipo',
            're_agendar' => 're-agendar',
            'pdf_servicio' => 'documento del servicio',
            'codigo_compra' => 'código de compra',
            'codigo_despacho' => 'código de despacho',
        ];
    }
}
