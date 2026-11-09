<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\ConceptoObjetivo;
use App\Enums\ModuloObjetivo;
use App\Enums\TenantPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Objetivos de un año y un tipo de equipo, mes a mes (desempeño por técnico): por cada mes,
 * un valor por concepto del módulo (vacío para no tener objetivo).
 */
class GuardarObjetivosMensualesRequest extends FormRequest
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
            'tipo_equipo_id' => ['required', 'integer', Rule::exists('tipo_equipos', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'meses' => ['present', 'array'],
        ];

        foreach (range(1, 12) as $mes) {
            foreach ($this->modulo()->conceptos() as $concepto) {
                $reglas["meses.{$mes}.{$concepto->value}"] = array_filter([
                    'nullable',
                    'numeric',
                    'min:0',
                    $concepto->maximo() !== null ? "max:{$concepto->maximo()}" : null,
                ]);
            }
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
            'tipo_equipo_id' => 'tipo de equipo',
            ...collect(range(1, 12))
                ->flatMap(fn (int $mes) => collect($this->modulo()->conceptos())
                    ->mapWithKeys(fn (ConceptoObjetivo $concepto) => [
                        "meses.{$mes}.{$concepto->value}" => mb_strtolower($concepto->label()).' del mes '.$mes,
                    ]))
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
