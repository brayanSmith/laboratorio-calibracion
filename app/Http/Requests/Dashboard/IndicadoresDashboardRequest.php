<?php

namespace App\Http\Requests\Dashboard;

use App\Actions\Dashboard\CalcularCumplimiento;
use App\Actions\Dashboard\CalcularDesempenoTecnicos;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Filtros de los indicadores del dashboard (tiempos de atención, desempeño y
 * cumplimiento). Cada indicador usa los que le aplican; el permiso de cada uno lo revisa
 * IndicadoresDashboardController.
 */
class IndicadoresDashboardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            // Selección múltiple (todos los indicadores): varias áreas, laboratorios
            // ("propio" o el id de una empresa tercera), técnicos, magnitudes y tipos de equipo.
            'areas' => ['nullable', 'array'],
            'areas.*' => ['integer', Rule::exists('areas', 'id')->where('tenant_id', $tenantId)],
            'laboratorios' => ['nullable', 'array'],
            'laboratorios.*' => [function (string $atributo, mixed $valor, Closure $fallar) use ($tenantId): void {
                $esEmpresa = is_numeric($valor)
                    && DB::table('empresa_terceros')->where('tenant_id', $tenantId)->where('id', (int) $valor)->exists();

                if ($valor !== 'propio' && ! $esEmpresa) {
                    $fallar(__('El laboratorio seleccionado no es válido.'));
                }
            }],
            'magnitudes' => ['nullable', 'array'],
            'magnitudes.*' => ['integer', Rule::exists('tipo_magnituds', 'id')->where('tenant_id', $tenantId)],
            'tipos_equipo' => ['nullable', 'array'],
            'tipos_equipo.*' => ['integer', Rule::exists('tipo_equipos', 'id')->where('tenant_id', $tenantId)],

            'tecnicos' => ['nullable', 'array'],
            'tecnicos.*' => ['integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],

            // Desempeño por técnico: qué servicios ver.
            'servicios' => ['nullable', 'array'],
            'servicios.*' => [Rule::in(CalcularDesempenoTecnicos::TIPOS_SERVICIO)],

            // Cumplimiento: tipo de mantenimiento del tipo de equipo (A, B o los dos).
            'tipos_mantenimiento' => ['nullable', 'array'],
            'tipos_mantenimiento.*' => [Rule::in(CalcularCumplimiento::TIPOS_MANTENIMIENTO)],
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
            'tecnicos.*' => 'técnico',
            'areas.*' => 'área',
            'laboratorios.*' => 'laboratorio',
            'magnitudes.*' => 'magnitud',
            'tipos_equipo.*' => 'tipo de equipo',
            'servicios.*' => 'servicio',
            'tipos_mantenimiento.*' => 'tipo de mantenimiento',
        ];
    }
}
