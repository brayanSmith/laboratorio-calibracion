<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlcancesMedicion\StoreMedicionAlcanceRequest;
use App\Http\Requests\AlcancesMedicion\UpdateMedicionAlcanceRequest;
use App\Models\DetalleMedicionAlcance;
use App\Models\EquipoEspecificacionTecnica;
use App\Models\MedicionAlcance;
use App\Models\TipoEquipo;
use App\Models\UnidadMedida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MedicionAlcanceController extends Controller
{
    /**
     * Display the alcances de medición of the tenant with their detalles.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MedicionAlcance::class);

        $tenantId = $request->user()->tenant_id;

        $alcancesMedicion = MedicionAlcance::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'tipoEquipo:id,nombre',
                'detalleMedicionAlcance' => fn ($query) => $query->with('unidadMedida:id,nombre,simbolo')->orderBy('id'),
            ])
            ->orderBy('tipo_equipo_id')
            ->orderBy('alcance_indicacion')
            ->get()
            ->map(fn (MedicionAlcance $medicionAlcance) => [
                'id' => $medicionAlcance->id,
                'tipo_equipo_id' => $medicionAlcance->tipo_equipo_id,
                'tipo_equipo' => $medicionAlcance->tipoEquipo->nombre,
                'alcance_indicacion' => $medicionAlcance->alcance_indicacion,
                'detalles' => $medicionAlcance->detalleMedicionAlcance->map(fn (DetalleMedicionAlcance $detalle) => [
                    'id' => $detalle->id,
                    'unidad_medida_id' => $detalle->unidad_medida_id,
                    'unidad_medida' => $detalle->unidadMedida->nombre,
                    'unidad_medida_simbolo' => $detalle->unidadMedida->simbolo,
                    'valor_instrumento' => $detalle->valor_instrumento,
                    'emp' => $detalle->emp,
                    'incertidumbre' => $detalle->incertidumbre,
                ])->values(),
            ]);

        return Inertia::render('alcances-medicion/index', [
            'alcancesMedicion' => $alcancesMedicion,
            'tiposEquipo' => TipoEquipo::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'alcancesIndicacion' => EquipoEspecificacionTecnica::query()
                ->join('equipos', 'equipos.id', '=', 'equipo_especificacion_tecnicas.equipo_id')
                ->where('equipo_especificacion_tecnicas.tenant_id', $tenantId)
                ->whereNull('equipos.deleted_at')
                ->distinct()
                ->orderBy('equipo_especificacion_tecnicas.alcance_indicacion')
                ->get(['equipos.tipo_equipo_id', 'equipo_especificacion_tecnicas.alcance_indicacion'])
                ->groupBy('tipo_equipo_id')
                ->map(fn ($alcances) => $alcances->pluck('alcance_indicacion')->values()),
            'unidadesMedida' => UnidadMedida::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'simbolo']),
        ]);
    }

    /**
     * Store a newly created alcance de medición.
     */
    public function store(StoreMedicionAlcanceRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        /** @var array<int, array<string, mixed>> $detalles */
        $detalles = $request->validated('detalles', []);

        DB::transaction(function () use ($request, $tenantId, $detalles): void {
            $medicionAlcance = MedicionAlcance::create([
                ...$request->safe()->only(['tipo_equipo_id', 'alcance_indicacion']),
                'tenant_id' => $tenantId,
            ]);

            $medicionAlcance->detalleMedicionAlcance()->createMany(
                array_map(fn (array $detalle) => [...$detalle, 'tenant_id' => $tenantId], $detalles),
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Alcance de medición creado.')]);

        return to_route('alcances-medicion.index');
    }

    /**
     * Update the specified alcance de medición.
     */
    public function update(UpdateMedicionAlcanceRequest $request, MedicionAlcance $medicionAlcance): RedirectResponse
    {
        $datos = $request->safe()->only(['tipo_equipo_id', 'alcance_indicacion']);

        if (! $request->boolean('sincronizar_detalles')) {
            $medicionAlcance->update($datos);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Alcance de medición actualizado.')]);

            return to_route('alcances-medicion.index');
        }

        /** @var array<int, array<string, mixed>> $detalles */
        $detalles = $request->validated('detalles') ?? [];
        $idsConservados = array_values(array_filter(array_column($detalles, 'id')));

        $eliminados = $medicionAlcance->detalleMedicionAlcance()->whereNotIn('id', $idsConservados);

        if ((clone $eliminados)->whereHas('detalleMedicionCalibracion')->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede quitar un detalle que ya se usó en calibraciones.'),
            ]);

            return back();
        }

        DB::transaction(function () use ($medicionAlcance, $datos, $detalles, $eliminados): void {
            $medicionAlcance->update($datos);
            $eliminados->delete();

            foreach ($detalles as $detalle) {
                $campos = collect($detalle)->except('id')->all();

                if (empty($detalle['id'])) {
                    $medicionAlcance->detalleMedicionAlcance()->create([
                        ...$campos,
                        'tenant_id' => $medicionAlcance->tenant_id,
                    ]);
                } else {
                    $medicionAlcance->detalleMedicionAlcance()->whereKey($detalle['id'])->first()?->update($campos);
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Alcance de medición actualizado.')]);

        return to_route('alcances-medicion.index');
    }

    /**
     * Remove the specified alcance de medición along with its detalles.
     */
    public function destroy(MedicionAlcance $medicionAlcance): RedirectResponse
    {
        Gate::authorize('delete', $medicionAlcance);

        $enUso = DetalleMedicionAlcance::query()
            ->where('medicion_alcance_id', $medicionAlcance->id)
            ->whereHas('detalleMedicionCalibracion')
            ->exists();

        if ($enUso) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar un alcance de medición que ya se usó en calibraciones.'),
            ]);

            return back();
        }

        DB::transaction(function () use ($medicionAlcance): void {
            $medicionAlcance->detalleMedicionAlcance()->delete();
            $medicionAlcance->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Alcance de medición eliminado.')]);

        return to_route('alcances-medicion.index');
    }
}
