<?php

namespace App\Http\Requests\Equipos;

use App\Models\EquipoProgramacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BuscarEquipoProgramacionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('viewAny', EquipoProgramacion::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'bahia_id' => ['required', Rule::exists('bahias', 'id')->where('tenant_id', $this->user()->tenant_id)->whereNull('deleted_at')],
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
            'desde' => 'fecha desde',
            'hasta' => 'fecha hasta',
            'bahia_id' => 'bahía',
        ];
    }
}
