<?php

namespace App\Modules\Objetivos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ObjetivoUser;
use App\Models\User;
use App\Modules\Catalogos\Models\TipoEquipo;
use App\Modules\Objetivos\Http\Requests\ObjetivoUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Módulo Objetivos: los objetivos por usuario (mantenimientos y calibraciones por mes, de
 * un año) y, en otra pestaña, los del dashboard.
 */
class ObjetivoUserController extends Controller
{
    /**
     * Display the objetivos of the tenant, one row per usuario and año.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ObjetivoUser::class);

        $tenantId = $request->user()->tenant_id;

        $objetivos = ObjetivoUser::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('mes')
            ->with('user:id,name')
            ->orderBy('mes')
            ->get()
            ->groupBy(fn (ObjetivoUser $objetivo): string => "{$objetivo->user_id}-{$objetivo->anio}")
            ->map(function (Collection $delAnio, string $clave): array {
                /** @var ObjetivoUser $primero */
                $primero = $delAnio->first();
                $porTipo = $delAnio->groupBy('tipo');

                return [
                    'clave' => $clave,
                    'user_id' => $primero->user_id,
                    'usuario' => $primero->user?->name,
                    'anio' => $primero->anio,
                    'tipos' => collect(ObjetivoUser::TIPOS)
                        ->mapWithKeys(fn (string $tipo): array => [$tipo => $this->resumenDelTipo($porTipo->get($tipo))])
                        ->all(),
                ];
            })
            ->sortBy([['anio', 'desc'], ['usuario', 'asc']])
            ->values();

        return Inertia::render('objetivos/index', [
            'objetivos' => $objetivos,
            'usuarios' => User::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $usuario): array => ['id' => $usuario->id, 'nombre' => $usuario->name]),
            'tiposEquipo' => TipoEquipo::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(fn (TipoEquipo $tipo): array => ['id' => $tipo->id, 'nombre' => $tipo->nombre]),
        ]);
    }

    /**
     * Save the 12 months of mantenimientos and calibraciones of a usuario and año; empty
     * months lose their objetivo.
     */
    public function store(ObjetivoUserRequest $request): RedirectResponse
    {
        $grupo = [
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->integer('user_id'),
            'anio' => $request->integer('anio'),
        ];

        DB::transaction(function () use ($grupo, $request): void {
            foreach (ObjetivoUser::TIPOS as $tipo) {
                $valores = $request->valoresPorMes($tipo);

                ObjetivoUser::query()
                    ->where($grupo)
                    ->where('tipo', $tipo)
                    ->whereNotNull('mes')
                    ->whereNotIn('mes', array_keys($valores))
                    ->delete();

                foreach ($valores as $mes => $valor) {
                    ObjetivoUser::updateOrCreate([...$grupo, 'tipo' => $tipo, 'mes' => $mes], ['valor' => $valor]);
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Objetivos guardados.')]);

        return to_route('objetivos.index');
    }

    /**
     * Remove every objetivo of a usuario in a año.
     */
    public function destroy(Request $request, User $usuario, int $anio): RedirectResponse
    {
        Gate::authorize('create', ObjetivoUser::class);
        abort_unless($usuario->tenant_id === $request->user()->tenant_id, 404);

        ObjetivoUser::query()
            ->where('tenant_id', $usuario->tenant_id)
            ->where('user_id', $usuario->id)
            ->where('anio', $anio)
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Objetivos eliminados.')]);

        return to_route('objetivos.index');
    }

    /**
     * Los meses y el total de un tipo en el año; null si no tiene objetivos.
     *
     * @param  Collection<int, ObjetivoUser>|null  $objetivos
     * @return array{meses: array<int, float>, total: float}|null
     */
    private function resumenDelTipo(?Collection $objetivos): ?array
    {
        if ($objetivos === null || $objetivos->isEmpty()) {
            return null;
        }

        return [
            'meses' => $objetivos
                ->mapWithKeys(fn (ObjetivoUser $objetivo): array => [$objetivo->mes => (float) $objetivo->valor])
                ->all(),
            'total' => round($objetivos->sum(fn (ObjetivoUser $objetivo): float => (float) $objetivo->valor), 2),
        ];
    }
}
