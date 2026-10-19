<?php

namespace App\Modules\Calibraciones\Concerns;

use App\Modules\Calibraciones\Http\Requests\FinalizarCalibracionRequest;
use App\Modules\Calibraciones\Http\Requests\UpdateCalibracionRequest;
use App\Modules\Calibraciones\Models\Calibracion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Reglas del formulario de edición de una calibración (condiciones ambientales, método,
 * novedad y valor del instrumento de cada resultado). Las comparten "Guardar cambios"
 * (UpdateCalibracionRequest) y "Finalizar" (FinalizarCalibracionRequest), que guarda el
 * mismo formulario en la misma petición.
 */
trait ReglasFormularioCalibracion
{
    /**
     * @param  bool  $alFinalizar  Al finalizar, la temperatura, la humedad y cada valor del
     *                             instrumento solo son obligatorios si el estado final es FINALIZADO.
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reglasFormulario(Calibracion $calibracion, bool $alFinalizar): array
    {
        $tenantId = $calibracion->tenant_id;
        $obligatorio = $alFinalizar ? ['required_if:estado_calibracion,FINALIZADO', 'nullable'] : ['required'];

        return [
            'procedimiento_id' => [
                'nullable',
                Rule::exists('procedimiento_calibracions', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'temperatura' => [...$obligatorio, 'numeric'],
            'humedad' => [...$obligatorio, 'numeric'],
            'ajustes_requeridos' => ['boolean'],
            'novedad_id' => [
                'nullable',
                Rule::exists('novedads', 'id')->where('tenant_id', $tenantId)->where('categoria', 'CALIBRACION')->whereNull('deleted_at'),
            ],
            'detalles' => ['array'],
            'detalles.*.id' => [
                'required',
                Rule::exists('detalle_medicion_calibracions', 'id')->where('calibracion_id', $calibracion->id),
            ],
            'detalles.*.valor_instrumento' => $alFinalizar
                ? ['required_if:estado_calibracion,FINALIZADO', 'nullable', 'numeric']
                : ['nullable', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function atributosFormulario(): array
    {
        return [
            'procedimiento_id' => 'procedimiento',
            'temperatura' => 'temperatura',
            'humedad' => 'humedad',
            'novedad_id' => 'novedad',
            'detalles.*.valor_instrumento' => 'valor del instrumento',
        ];
    }
}
