<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\ConceptoObjetivo;
use App\Enums\ModuloObjetivo;
use App\Enums\TenantPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Objetivos de un año para un módulo del dashboard: un valor por cada concepto del módulo
 * (vacío para no tener objetivo en ese concepto).
 */
class GuardarObjetivosDashboardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(TenantPermission::DashboardObjetivos->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = [
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'valores' => ['present', 'array'],
        ];

        foreach ($this->modulo()->conceptos() as $concepto) {
            $reglas["valores.{$concepto->value}"] = array_filter([
                'nullable',
                // Las cantidades son enteras; los tiempos y el porcentaje admiten decimales.
                $concepto->formato() === 'cantidad' ? 'integer' : 'numeric',
                'min:0',
                $concepto->maximo() !== null ? "max:{$concepto->maximo()}" : null,
            ]);
        }

        return $reglas;
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'anio' => 'año',
            ...collect($this->modulo()->conceptos())
                ->mapWithKeys(fn (ConceptoObjetivo $concepto) => [
                    "valores.{$concepto->value}" => mb_strtolower($concepto->label()),
                ])
                ->all(),
        ];
    }

    /**
     * Módulo de la ruta.
     */
    public function modulo(): ModuloObjetivo
    {
        return $this->route('modulo');
    }
}
