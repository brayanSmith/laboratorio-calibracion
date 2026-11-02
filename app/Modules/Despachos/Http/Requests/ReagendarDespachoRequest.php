<?php

namespace App\Modules\Despachos\Http\Requests;

use App\Modules\Despachos\Models\Despacho;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class ReagendarDespachoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('despacho'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha_programada' => ['required', 'date', 'after_or_equal:today'],
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Un despacho ya entregado no se re-agenda.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Despacho $despacho */
                $despacho = $this->route('despacho');

                if ($despacho->entrega_recibida) {
                    $validator->errors()->add('fecha_programada', 'El despacho ya se entregó: no se puede re-agendar.');
                }
            },
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_programada.after_or_equal' => 'La nueva fecha no puede ser anterior a hoy.',
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
            'fecha_programada' => 'nueva fecha programada',
            'motivo' => 'motivo',
        ];
    }
}
