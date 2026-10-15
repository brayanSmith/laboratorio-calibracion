<?php

namespace App\Http\Controllers;

use App\Concerns\ListaEquipos;
use App\Models\Equipo;
use App\Models\EquipoProgramacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ListaEquipos;

    /**
     * Máximo de equipos que muestra el listado de atención urgente.
     */
    private const MAX_URGENTES = 5;

    /**
     * Display the dashboard: cuántos equipos están al día, próximos a vencer y vencidos,
     * con el mismo criterio del listado de equipos (ver
     * EquipoProgramacion::vencimientoPorEquipo()), los más urgentes y la tabla de equipos
     * que las tarjetas filtran por `filtro[vencimiento]`. Los equipos solo se envían a
     * quien puede verlos.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user()->is_platform_admin) {
            return to_route('plataforma.dashboard');
        }

        $tenantId = $request->user()->tenant_id;
        $estadoPorEquipo = EquipoProgramacion::vencimientoPorEquipo($tenantId);
        $porEstado = $estadoPorEquipo->countBy();
        $puedeVerEquipos = Gate::allows('viewAny', Equipo::class);

        return Inertia::render('dashboard', [
            'total_equipos' => Equipo::query()->where('tenant_id', $tenantId)->count(),
            'vencimientos' => [
                'al_dia' => $porEstado->get('AL_DIA', 0),
                'proximos_a_vencer' => $porEstado->get('PROXIMO_A_VENCER', 0),
                'vencidos' => $porEstado->get('VENCIDO', 0),
            ],
            ...($puedeVerEquipos ? [
                'equipos_urgentes' => $this->equiposUrgentes($tenantId, $estadoPorEquipo),
                ...$this->datosListadoEquipos($request),
            ] : []),
        ]);
    }

    /**
     * Hasta MAX_URGENTES equipos vencidos y, si sobra lugar, próximos a vencer: primero los
     * vencidos, y dentro de cada estado el de fecha de próximo servicio más antigua.
     *
     * @param  Collection<int, string>  $estadoPorEquipo
     * @return list<array<string, mixed>> El equipo completo, más su estado de vencimiento y la fecha del próximo servicio
     */
    private function equiposUrgentes(int $tenantId, Collection $estadoPorEquipo): array
    {
        $urgentes = $estadoPorEquipo->filter(fn (string $estado) => $estado !== 'AL_DIA');

        return Equipo::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $urgentes->keys())
            ->with($this->relacionesListadoEquipos())
            ->get()
            ->map(function (Equipo $equipo) use ($urgentes): array {
                $estado = $urgentes[$equipo->id];

                return [
                    ...$this->conDocumentos($equipo),
                    'estado_vencimiento' => $estado,
                    'fecha_proximo_servicio' => $equipo->equipoProgramaciones
                        ->where('estado_programacion', '!=', 'RECIBIDO')
                        ->filter(fn (EquipoProgramacion $programacion) => $programacion->estado_vencimiento === $estado)
                        ->map(fn (EquipoProgramacion $programacion) => $programacion->fecha_proximo_servicio?->toDateString())
                        ->filter()
                        ->min(),
                ];
            })
            ->sortBy([
                fn (array $a, array $b) => ($b['estado_vencimiento'] === 'VENCIDO') <=> ($a['estado_vencimiento'] === 'VENCIDO'),
                fn (array $a, array $b) => ($a['fecha_proximo_servicio'] ?? '9999') <=> ($b['fecha_proximo_servicio'] ?? '9999'),
            ])
            ->take(self::MAX_URGENTES)
            ->values()
            ->all();
    }
}
