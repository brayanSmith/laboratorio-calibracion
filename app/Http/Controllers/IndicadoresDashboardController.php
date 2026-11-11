<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\CalcularCumplimiento;
use App\Actions\Dashboard\CalcularDesempenoTecnicos;
use App\Actions\Dashboard\CalcularTiemposAtencion;
use App\Actions\Dashboard\CalcularTrazaNoRecibido;
use App\Enums\ModuloObjetivo;
use App\Enums\TenantPermission;
use App\Http\Requests\Dashboard\IndicadoresDashboardRequest;
use App\Models\ObjetivoDashboard;
use App\Models\ObjetivoUser;
use App\Modules\Equipos\Models\EquipoProgramacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return $this->conObjetivos($request, ModuloObjetivo::TiemposAtencion, $calcular->handle($request->user()->tenant_id, $this->filtrosMultiples($request)));
    }

    /**
     * Cantidad y tiempo promedio de mantenimientos y calibraciones por técnico o empresa tercera.
     */
    public function desempenoTecnicos(IndicadoresDashboardRequest $request, CalcularDesempenoTecnicos $calcular): JsonResponse
    {
        abort_unless($request->user()->can(TenantPermission::DashboardDesempenoTecnicos->value), 403);

        return $this->conObjetivos($request, ModuloObjetivo::DesempenoTecnicos, $calcular->handle($request->user()->tenant_id, $this->filtrosMultiples($request)));
    }

    /**
     * Equipos con servicio programado en el rango frente a los que se atendieron.
     */
    public function cumplimiento(IndicadoresDashboardRequest $request, CalcularCumplimiento $calcular): JsonResponse
    {
        abort_unless($request->user()->can(TenantPermission::DashboardCumplimiento->value), 403);

        return $this->conObjetivos($request, ModuloObjetivo::Cumplimiento, $calcular->handle($request->user()->tenant_id, $this->filtrosMultiples($request)));
    }

    /**
     * Traza de un equipo no recibido en el ingreso (de la tabla de cumplimiento): su
     * seguimiento, el re-agendamiento y lo que pasó después con el equipo.
     */
    public function trazaNoRecibido(Request $request, EquipoProgramacion $programacion, CalcularTrazaNoRecibido $calcular): JsonResponse
    {
        abort_unless($request->user()->can(TenantPermission::DashboardCumplimiento->value), 403);
        abort_unless($programacion->tenant_id === $request->user()->tenant_id, 404);

        return response()->json($calcular->handle($programacion));
    }

    /**
     * Agrega al indicador los objetivos de cada año del periodo (si el periodo abarca dos
     * años, cada uno con los suyos), para dibujarlos en sus gráficos.
     *
     * @param  array<string, mixed>  $indicador
     */
    private function conObjetivos(IndicadoresDashboardRequest $request, ModuloObjetivo $modulo, array $indicador): JsonResponse
    {
        $anios = range(
            (int) substr($request->validated('desde'), 0, 4),
            (int) substr($request->validated('hasta'), 0, 4),
        );
        // Desempeño: los tiempos por mes y tipo de equipo, solo de los tipos filtrados (sin
        // filtro, de todos); las cantidades, los objetivos de cada técnico filtrado (sin
        // filtro, de todos los usuarios). El gráfico y el detalle los combinan.
        if ($modulo->porMesYTipoEquipo()) {
            return response()->json([
                ...$indicador,
                'objetivos' => ObjetivoDashboard::mensualesPorTipo(
                    $request->user()->tenant_id,
                    $modulo,
                    $anios,
                    array_map('intval', $request->validated('tipos_equipo', [])),
                ),
                // Las empresas terceras no tienen objetivos de cantidad.
                'objetivos_usuarios' => $request->validated('origen') === 'TERCERO' ? [] : ObjetivoUser::porMes(
                    $request->user()->tenant_id,
                    $anios,
                    array_map('intval', $request->validated('tecnicos', [])),
                ),
            ]);
        }

        $porAnio = ObjetivoDashboard::porAnio($request->user()->tenant_id, $modulo, $anios);

        return response()->json([
            ...$indicador,
            'objetivos' => array_map(fn (int $anio): array => [
                'anio' => $anio,
                // Un objeto aunque esté vacío, para que el frontend lo lea igual.
                'valores' => (object) ($porAnio[$anio] ?? []),
            ], $anios),
        ]);
    }

    /**
     * Filtros de selección múltiple de los indicadores: listas de ids como enteros; en los
     * laboratorios, "propio" se mantiene como texto. Cada indicador usa los que le aplican.
     *
     * @return array{desde: string, hasta: string, areas: list<int>, laboratorios: list<int|string>, tecnicos: list<int>, magnitudes: list<int>, tipos_equipo: list<int>, servicios: list<string>, origen: string|null, tipos_mantenimiento: list<string>}
     */
    private function filtrosMultiples(IndicadoresDashboardRequest $request): array
    {
        $enteros = fn (string $campo): array => array_map('intval', $request->validated($campo, []));

        return [
            'desde' => $request->validated('desde'),
            'hasta' => $request->validated('hasta'),
            'areas' => $enteros('areas'),
            'laboratorios' => array_map(
                fn (string|int $laboratorio): string|int => $laboratorio === 'propio' ? $laboratorio : (int) $laboratorio,
                $request->validated('laboratorios', []),
            ),
            'tecnicos' => $enteros('tecnicos'),
            'magnitudes' => $enteros('magnitudes'),
            'tipos_equipo' => $enteros('tipos_equipo'),
            'servicios' => $request->validated('servicios', []),
            'origen' => $request->validated('origen'),
            'tipos_mantenimiento' => $request->validated('tipos_mantenimiento', []),
        ];
    }
}
