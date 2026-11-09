<?php

namespace App\Http\Controllers;

use App\Enums\ConceptoObjetivo;
use App\Enums\ModuloObjetivo;
use App\Enums\TenantPermission;
use App\Http\Requests\Dashboard\GuardarObjetivosMensualesRequest;
use App\Models\ObjetivoDashboard;
use App\Modules\Catalogos\Models\TipoEquipo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Objetivos por mes y tipo de equipo de un módulo del dashboard (desempeño por técnico): se
 * cargan por año y tipo de equipo, los 12 meses a la vez.
 */
class ObjetivosMensualesDashboardController extends Controller
{
    /**
     * Conceptos del módulo, tipos de equipo y lo que ya tiene cada año y tipo, mes a mes.
     */
    public function index(Request $request, ModuloObjetivo $modulo): JsonResponse
    {
        abort_unless($request->user()->can(TenantPermission::DashboardObjetivos->value), 403);
        abort_unless($modulo->porMesYTipoEquipo(), 404);

        return response()->json($this->datos($request->user()->tenant_id, $modulo));
    }

    /**
     * Guarda los 12 meses de un año y tipo de equipo: crea o actualiza cada concepto con
     * valor y quita los que quedaron vacíos.
     */
    public function store(GuardarObjetivosMensualesRequest $request, ModuloObjetivo $modulo): JsonResponse
    {
        abort_unless($modulo->porMesYTipoEquipo(), 404);

        $tenantId = $request->user()->tenant_id;
        $anio = (int) $request->validated('anio');
        $tipoEquipoId = (int) $request->validated('tipo_equipo_id');
        $meses = $request->validated('meses', []);

        DB::transaction(function () use ($modulo, $tenantId, $anio, $tipoEquipoId, $meses): void {
            foreach (range(1, 12) as $mes) {
                foreach ($modulo->conceptos() as $concepto) {
                    $valor = $meses[$mes][$concepto->value] ?? null;
                    $clave = [
                        'tenant_id' => $tenantId,
                        'anio' => $anio,
                        'mes' => $mes,
                        'tipo_equipo_id' => $tipoEquipoId,
                        'concepto' => $concepto,
                    ];

                    if ($valor === null) {
                        ObjetivoDashboard::query()->where($clave)->delete();

                        continue;
                    }

                    ObjetivoDashboard::query()->updateOrCreate($clave, ['valor' => $valor]);
                }
            }
        });

        return response()->json($this->datos($tenantId, $modulo));
    }

    /**
     * Quita los objetivos de un año y tipo de equipo del módulo.
     */
    public function destroy(Request $request, ModuloObjetivo $modulo, int $anio, int $tipoEquipo): JsonResponse
    {
        abort_unless($request->user()->can(TenantPermission::DashboardObjetivos->value), 403);
        abort_unless($modulo->porMesYTipoEquipo(), 404);

        $tenantId = $request->user()->tenant_id;

        ObjetivoDashboard::query()
            ->where('tenant_id', $tenantId)
            ->where('modulo', $modulo)
            ->where('anio', $anio)
            ->where('tipo_equipo_id', $tipoEquipo)
            ->delete();

        return response()->json($this->datos($tenantId, $modulo));
    }

    /**
     * @return array{conceptos: list<array<string, mixed>>, tipos_equipo: list<array{id: int, nombre: string}>, registros: list<array<string, mixed>>}
     */
    private function datos(int $tenantId, ModuloObjetivo $modulo): array
    {
        $tipos = TipoEquipo::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
        $nombres = $tipos->pluck('nombre', 'id');

        // Un registro por año y tipo de equipo, con sus meses: [mes => [concepto => valor]].
        $registros = ObjetivoDashboard::query()
            ->where('tenant_id', $tenantId)
            ->where('modulo', $modulo)
            ->whereNotNull('mes')
            ->get()
            ->groupBy(fn (ObjetivoDashboard $objetivo): string => "{$objetivo->anio}|{$objetivo->tipo_equipo_id}")
            ->map(fn ($objetivos): array => [
                'anio' => $objetivos->first()->anio,
                'tipo_equipo_id' => $objetivos->first()->tipo_equipo_id,
                'tipo_equipo' => $nombres[$objetivos->first()->tipo_equipo_id] ?? '—',
                // Un objeto aunque falten meses, para que el frontend lo lea igual.
                'meses' => (object) $objetivos
                    ->groupBy('mes')
                    ->map(fn ($delMes) => $delMes
                        ->mapWithKeys(fn (ObjetivoDashboard $objetivo) => [$objetivo->concepto->value => (float) $objetivo->valor])
                        ->all())
                    ->all(),
            ])
            ->sortBy([['anio', 'desc'], ['tipo_equipo', 'asc']])
            ->values()
            ->all();

        return [
            'conceptos' => array_map(fn (ConceptoObjetivo $concepto): array => [
                'concepto' => $concepto->value,
                'label' => $concepto->label(),
                'unidad' => $concepto->unidad(),
                'formato' => $concepto->formato(),
                'maximo' => $concepto->maximo(),
            ], $modulo->conceptos()),
            'tipos_equipo' => $tipos->map(fn (TipoEquipo $tipo): array => ['id' => $tipo->id, 'nombre' => $tipo->nombre])->all(),
            'registros' => $registros,
        ];
    }
}
