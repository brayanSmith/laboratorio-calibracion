<?php

namespace App\Http\Controllers;

use App\Enums\ConceptoObjetivo;
use App\Enums\ModuloObjetivo;
use App\Enums\TenantPermission;
use App\Http\Requests\Dashboard\GuardarObjetivosDashboardRequest;
use App\Models\ObjetivoDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Objetivos anuales y generales de los módulos del dashboard (los que se dibujan como línea en sus
 * gráficos). Un objetivo por año y concepto para todo el laboratorio; los de desempeño por
 * técnico, que son por mes y tipo de equipo, van en ObjetivosMensualesDashboardController.
 */
class ObjetivosDashboardController extends Controller
{
    /**
     * Conceptos del módulo y los objetivos de cada año que ya tiene.
     */
    public function index(Request $request, ModuloObjetivo $modulo): JsonResponse
    {
        abort_if($modulo->porMesYTipoEquipo(), 404);
        abort_unless($request->user()->can(TenantPermission::DashboardObjetivos->value), 403);

        return response()->json($this->datos($request->user()->tenant_id, $modulo));
    }

    /**
     * Guarda los objetivos de un año del módulo: crea o actualiza cada concepto con valor
     * y quita los que quedaron vacíos.
     */
    public function store(GuardarObjetivosDashboardRequest $request, ModuloObjetivo $modulo): JsonResponse
    {
        abort_if($modulo->porMesYTipoEquipo(), 404);
        $tenantId = $request->user()->tenant_id;
        $anio = (int) $request->validated('anio');
        $valores = $request->validated('valores', []);

        DB::transaction(function () use ($modulo, $tenantId, $anio, $valores): void {
            foreach ($modulo->conceptos() as $concepto) {
                $valor = $valores[$concepto->value] ?? null;
                $clave = ['tenant_id' => $tenantId, 'anio' => $anio, 'mes' => null, 'tipo_equipo_id' => null, 'concepto' => $concepto];

                if ($valor === null) {
                    ObjetivoDashboard::query()->where($clave)->delete();

                    continue;
                }

                ObjetivoDashboard::query()->updateOrCreate($clave, ['valor' => $valor]);
            }
        });

        return response()->json($this->datos($tenantId, $modulo));
    }

    /**
     * Quita todos los objetivos de un año del módulo.
     */
    public function destroy(Request $request, ModuloObjetivo $modulo, int $anio): JsonResponse
    {
        abort_if($modulo->porMesYTipoEquipo(), 404);
        abort_unless($request->user()->can(TenantPermission::DashboardObjetivos->value), 403);

        $tenantId = $request->user()->tenant_id;

        ObjetivoDashboard::query()
            ->where('tenant_id', $tenantId)
            ->where('modulo', $modulo)
            ->where('anio', $anio)
            ->whereNull('mes')
            ->delete();

        return response()->json($this->datos($tenantId, $modulo));
    }

    /**
     * @return array{conceptos: list<array<string, mixed>>, anios: list<array{anio: int, valores: array<string, float>}>}
     */
    private function datos(int $tenantId, ModuloObjetivo $modulo): array
    {
        $anios = ObjetivoDashboard::query()
            ->where('tenant_id', $tenantId)
            ->where('modulo', $modulo)
            ->whereNull('mes')
            ->orderByDesc('anio')
            ->get()
            ->groupBy('anio')
            ->map(fn ($objetivos, int $anio): array => [
                'anio' => $anio,
                'valores' => $objetivos
                    ->mapWithKeys(fn (ObjetivoDashboard $objetivo) => [$objetivo->concepto->value => (float) $objetivo->valor])
                    ->all(),
            ])
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
            'anios' => $anios,
        ];
    }
}
