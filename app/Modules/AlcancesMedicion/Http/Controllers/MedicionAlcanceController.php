<?php

namespace App\Modules\AlcancesMedicion\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AlcancesMedicion\Http\Requests\StoreMedicionAlcanceRequest;
use App\Modules\AlcancesMedicion\Http\Requests\UpdateMedicionAlcanceRequest;
use App\Modules\AlcancesMedicion\Models\DetalleMedicionAlcance;
use App\Modules\AlcancesMedicion\Models\MedicionAlcance;
use App\Modules\Catalogos\Models\TipoEquipo;
use App\Modules\Catalogos\Models\UnidadMedida;
use App\Modules\Equipos\Models\EquipoEspecificacionTecnica;
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
            ->orderBy('modelo')
            ->orderBy('alcance_indicacion')
            ->get()
            ->map(fn (MedicionAlcance $medicionAlcance) => [
                'id' => $medicionAlcance->id,
                'tipo_equipo_id' => $medicionAlcance->tipo_equipo_id,
                'tipo_equipo' => $medicionAlcance->tipoEquipo->nombre,
                'modelo' => $medicionAlcance->modelo,
                'alcance_indicacion' => $medicionAlcance->alcance_indicacion,
                'detalles' => $medicionAlcance->detalleMedicionAlcance->map(fn (DetalleMedicionAlcance $detalle) => [
                    'id' => $detalle->id,
                    'unidad_medida_id' => $detalle->unidad_medida_id,
                    'unidad_medida' => $detalle->unidadMedida->nombre,
                    'unidad_medida_simbolo' => $detalle->unidadMedida->simbolo,
                    'valor_referencia' => $detalle->valor_referencia,
                    'emp' => $detalle->emp,
                    'emp_porcentaje' => $detalle->emp_porcentaje,
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
                ->get(['equipos.tipo_equipo_id', 'equipos.modelo', 'equipo_especificacion_tecnicas.alcance_indicacion'])
                ->groupBy('tipo_equipo_id')
                ->map(fn ($alcances) => $alcances->groupBy('modelo')
                    ->map(fn ($delModelo) => $delModelo->pluck('alcance_indicacion')->values())),
            'especificaciones' => EquipoEspecificacionTecnica::query()
                ->join('equipos', 'equipos.id', '=', 'equipo_especificacion_tecnicas.equipo_id')
                ->where('equipo_especificacion_tecnicas.tenant_id', $tenantId)
                ->whereNull('equipos.deleted_at')
                ->orderBy('equipo_especificacion_tecnicas.id')
                ->get(['equipos.tipo_equipo_id', 'equipos.modelo', 'equipo_especificacion_tecnicas.alcance_indicacion', 'equipo_especificacion_tecnicas.precision', 'equipo_especificacion_tecnicas.unidad_medida_id'])
                ->groupBy(fn (EquipoEspecificacionTecnica $especificacion) => "{$especificacion->tipo_equipo_id}|{$especificacion->modelo}|{$especificacion->alcance_indicacion}")
                ->map(fn ($especificaciones) => [
                    'precision' => $especificaciones->first()->precision,
                    'unidad_medida_id' => $especificaciones->first()->unidad_medida_id,
                ]),
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
                ...$request->safe()->only(['tipo_equipo_id', 'modelo', 'alcance_indicacion']),
                'tenant_id' => $tenantId,
            ]);

            $medicionAlcance->detalleMedicionAlcance()->createMany(
                array_map(fn (array $detalle) => ['emp' => null, 'emp_porcentaje' => null, ...$detalle, 'tenant_id' => $tenantId], $detalles),
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
        $datos = $request->safe()->only(['tipo_equipo_id', 'modelo', 'alcance_indicacion']);

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
                $campos = ['emp' => null, 'emp_porcentaje' => null, ...collect($detalle)->except('id')->all()];

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
