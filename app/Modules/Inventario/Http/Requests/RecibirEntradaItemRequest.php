<?php

namespace App\Modules\Inventario\Http\Requests;

use App\Modules\Inventario\Models\EntradaItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RecibirEntradaItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var EntradaItem $entradaItem */
        $entradaItem = $this->route('entradaItem');

        return Gate::allows('recibirEntradas', $entradaItem->item);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha_recibido' => ['required', 'date'],
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
            'fecha_recibido' => 'fecha de recibido',
        ];
    }
}
