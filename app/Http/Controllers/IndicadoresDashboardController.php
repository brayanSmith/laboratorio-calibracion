<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\CalcularCumplimiento;
use App\Actions\Dashboard\CalcularDesempenoTecnicos;
use App\Actions\Dashboard\CalcularTiemposAtencion;
use App\Enums\TenantPermission;
use App\Http\Requests\Dashboard\IndicadoresDashboardRequest;
use Illuminate\Http\JsonResponse;

/**
 * Indicadores del dashboard. Cada uno se pide por separado (según sus filtros) y solo lo
 * puede ver quien tiene su permiso.
 */
class IndicadoresDashboardController extends Controller
{
    /**
     * Días entre la recepción en el ingreso y la entrega en el despacho de cada equipo.
     */
    public function tiemposAtencion(IndicadoresDashboardRequest $request, CalcularTiemposAtencion $calcular): JsonResponse
    {
        abort_unless($request->user()->can(TenantPermission::DashboardTiemposAtencion->value), 403);

        return response()->json($calcular->handle($request->user()->tenant_id, $this->filtros($request)));
    }

    /**
     * Cantidad y tiempo promedio de mantenimientos y calibraciones por técnico o empresa tercera.
     */
    public function desempenoTecnicos(IndicadoresDashboardRequest $request, CalcularDesempenoTecnicos $calcular): JsonResponse
    {
        abort_unless($request->user()->can(TenantPermission::DashboardDesempenoTecnicos->value), 403);

        return response()->json($calcular->handle($request->user()->tenant_id, $this->filtros($request)));
    }

    /**
     * Equipos con servicio programado en el rango frente a los que se atendieron.
     */
    public function cumplimiento(IndicadoresDashboardRequest $request, CalcularCumplimiento $calcular): JsonResponse
    {
        abort_unless($request->user()->can(TenantPermission::DashboardCumplimiento->value), 403);

        return response()->json($calcular->handle($request->user()->tenant_id, $this->filtros($request)));
    }

    /**
     * Filtros validados, con los ids como enteros ("propio" se mantiene como texto).
     *
     * @return array{desde: string, hasta: string, laboratorio: string|int|null, tecnico_id: int|null, tipo_equipo_id: int|null, area_id: int|null}
     */
    private function filtros(IndicadoresDashboardRequest $request): array
    {
        $laboratorio = $request->validated('laboratorio');
        $entero = fn (string $campo): ?int => $request->filled($campo) ? (int) $request->validated($campo) : null;

        return [
            'desde' => $request->validated('desde'),
            'hasta' => $request->validated('hasta'),
            'laboratorio' => $laboratorio === null || $laboratorio === 'propio' ? $laboratorio : (int) $laboratorio,
            'tecnico_id' => $entero('tecnico_id'),
            'tipo_equipo_id' => $entero('tipo_equipo_id'),
            'area_id' => $entero('area_id'),
        ];
    }
}
