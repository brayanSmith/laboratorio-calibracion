<?php

namespace App\Modules\Equipos\Http\Controllers;

use App\Concerns\ListaEquipos;
use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Modules\Calibraciones\Http\Controllers\CalibracionController;
use App\Modules\Catalogos\Models\Novedad;
use App\Modules\Despachos\Http\Controllers\DespachoController;
use App\Modules\Equipos\Http\Requests\StoreEquipoRequest;
use App\Modules\Equipos\Http\Requests\UpdateEquipoRequest;
use App\Modules\Equipos\Models\DocumentoEquipo;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoHistorico;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Ingresos\Http\Controllers\IngresoController;
use App\Modules\Mantenimientos\Http\Controllers\MantenimientoController;
use App\Modules\ServiciosTerceros\Http\Controllers\ServicioTerceroController;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EquipoController extends Controller
{
    use ListaEquipos;

    /**
     * Display a listing of the tenant's equipos, optionally filtered by column
     * (`filtro[codigo]`, `modelo`, `tipo_equipo`, `fabricante`, `cliente`, `vencimiento`, `activo`).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Equipo::class);

        return Inertia::render('equipos/index', $this->datosListadoEquipos($request));
    }

    /**
     * Store a newly created equipo, along with its especificación técnica,
     * programación de servicio and documentos when they were filled in.
     */
    public function store(StoreEquipoRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $equipo = DB::transaction(function () use ($request, $tenantId): Equipo {
            $equipo = Equipo::create([
                ...$request->safe()->only([
                    'codigo', 'tipo_equipo_id', 'tipo_tecnologia', 'modelo', 'fabricante_id',
                    'numero_serie', 'area_id', 'bahia_id', 'condicion_actual', 'notas', 'activo',
                    'patron_referencia', 'requiere_programacion', 'cliente_id',
                ]),
                'ficha_tecnica' => $this->fichaTecnica($request),
                'tenant_id' => $tenantId,
            ]);

            if ($request->filled('tipo_magnitud_id')) {
                $equipo->equipoEspecificacionTecnica()->create([
                    ...$request->safe()->only([
                        'tipo_magnitud_id', 'unidad_medida_id', 'alcance_indicacion', 'precision', 'resolucion',
                    ]),
                    'tenant_id' => $tenantId,
                ]);
            }

            foreach ($request->validated('programaciones', []) as $programacion) {
                $equipo->equipoProgramaciones()->create([
                    ...$programacion,
                    'tipo_servicio' => implode(',', $programacion['tipo_servicio']),
                    'fecha_proximo_servicio' => EquipoProgramacion::calcularFechaProximoServicio(
                        $programacion['fecha_ultimo_servicio'] ?? null,
                        $programacion['intervalo_servicio'] ?? null,
                        $programacion['intervalo_unidad'] ?? null,
                    ),
                    'tenant_id' => $tenantId,
                ]);
            }

            foreach ($request->validated('documentos', []) as $documento) {
                $equipo->equipoDocumentos()->create([
                    'nombre' => $documento['nombre'],
                    'archivo' => $documento['archivo']->store("documentos/{$tenantId}/{$equipo->id}", 'public'),
                    'tenant_id' => $tenantId,
                ]);
            }

            return $equipo;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Equipo creado.')]);

        return to_route('equipos.index');
    }

    /**
     * Display the specified equipo.
     */
    public function show(Request $request, Equipo $equipo): Response
    {
        Gate::authorize('view', $equipo);

        $equipo->load([
            'tipoEquipo:id,nombre',
            'fabricante:id,nombre',
            'area:id,nombre',
            'bahia:id,nombre',
            'cliente:id,nombre',
            'equipoEspecificacionTecnica',
            'equipoProgramaciones' => fn ($query) => $query->latest()->with([
                'seguimientos' => fn ($seguimientos) => $seguimientos->with('usuario:id,name')->latest('id'),
            ]),
        ]);

        return Inertia::render('equipos/show', [
            'equipo' => $this->conDocumentos($equipo),
            'options' => $this->formOptions($equipo->tenant_id),
        ]);
    }

    /**
     * Historial de servicios del equipo: los ingresos en que llegó y los mantenimientos,
     * calibraciones, despachos y servicios de terceros de sus órdenes de trabajo, del más
     * reciente al más antiguo. Cada uno con los mismos datos que su listado, para abrir su
     * modal "Ver". Los servicios con técnico responsable se limitan a los que el usuario
     * puede ver, y los servicios de terceros a los tipos (mantenimiento o calibración) de
     * terceros que tiene permitidos. Incluye también los servicios históricos importados, el
     * laboratorio propio (para los históricos que no hizo un tercero) y las empresas
     * terceras, para elegirla al agregar uno.
     */
    public function historial(Request $request, Equipo $equipo): JsonResponse
    {
        Gate::authorize('verHistorial', $equipo);

        $user = $request->user();

        return response()->json([
            'ingresos' => IngresoController::listar($equipo->tenant_id, $equipo->id),
            'mantenimientos' => MantenimientoController::listar($user, $equipo->id),
            'calibraciones' => CalibracionController::listar($user, $equipo->id),
            'despachos' => DespachoController::listar($user, $equipo->id),
            'servicios_terceros' => ServicioTerceroController::listar($equipo->tenant_id, null, $equipo->id)
                ->filter(fn (array $servicio) => $user->can(TenantPermission::tercerosDe($servicio['tipo_servicio'])->value))
                ->values(),
            'historicos' => $equipo->equipoHistoricos()
                ->with('empresaTercero:id,nombre')
                ->orderByDesc('fecha_servicio')
                ->orderByDesc('id')
                ->get()
                ->map(fn (EquipoHistorico $historico) => $historico->resumen()),
            'novedadesIngreso' => Novedad::query()->where('tenant_id', $equipo->tenant_id)->where('categoria', 'INGRESO')->orderBy('nombre')->get(['id', 'nombre']),
            'laboratorioPropio' => Empresa::laboratorioDelTenant($equipo->tenant_id),
            'empresasTerceras' => EmpresaTercero::query()->where('tenant_id', $equipo->tenant_id)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /**
     * Update the specified equipo, along with its especificación técnica,
     * programaciones de servicio and documentos, all in a single submit.
     */
    public function update(UpdateEquipoRequest $request, Equipo $equipo): RedirectResponse
    {
        DB::transaction(function () use ($request, $equipo): void {
            $equipo->update([
                ...$request->safe()->only([
                    'codigo', 'tipo_equipo_id', 'tipo_tecnologia', 'modelo', 'fabricante_id',
                    'numero_serie', 'area_id', 'bahia_id', 'condicion_actual', 'notas', 'activo',
                    'patron_referencia', 'requiere_programacion', 'cliente_id',
                ]),
                'ficha_tecnica' => $this->fichaTecnica($request),
            ]);

            if ($request->filled('tipo_magnitud_id')) {
                $equipo->equipoEspecificacionTecnica()->updateOrCreate([], [
                    ...$request->safe()->only([
                        'tipo_magnitud_id', 'unidad_medida_id', 'alcance_indicacion', 'precision', 'resolucion',
                    ]),
                    'tenant_id' => $equipo->tenant_id,
                ]);
            } else {
                $equipo->equipoEspecificacionTecnica()->delete();
            }

            EquipoProgramacion::whereIn('id', $request->validated('programaciones_eliminar', []))->delete();

            foreach ($request->validated('programaciones', []) as $programacion) {
                $equipo->equipoProgramaciones()->create([
                    ...$programacion,
                    'tipo_servicio' => implode(',', $programacion['tipo_servicio']),
                    'fecha_proximo_servicio' => EquipoProgramacion::calcularFechaProximoServicio(
                        $programacion['fecha_ultimo_servicio'] ?? null,
                        $programacion['intervalo_servicio'] ?? null,
                        $programacion['intervalo_unidad'] ?? null,
                    ),
                    'tenant_id' => $equipo->tenant_id,
                ]);
            }

            $documentosEliminar = DocumentoEquipo::whereIn('id', $request->validated('documentos_eliminar', []))->get();

            foreach ($documentosEliminar as $documento) {
                Storage::disk('public')->delete($documento->archivo);
                $documento->delete();
            }

            foreach ($request->validated('documentos', []) as $documento) {
                $equipo->equipoDocumentos()->create([
                    'nombre' => $documento['nombre'],
                    'archivo' => $documento['archivo']->store("documentos/{$equipo->tenant_id}/{$equipo->id}", 'public'),
                    'tenant_id' => $equipo->tenant_id,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Equipo actualizado.')]);

        return back();
    }

    /**
     * Remove the specified equipo.
     */
    public function destroy(Request $request, Equipo $equipo): RedirectResponse
    {
        Gate::authorize('delete', $equipo);

        $equipo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Equipo eliminado.')]);

        return to_route('equipos.index');
    }

    /**
     * Build the ficha_tecnica JSON payload from the request's validated data.
     *
     * @return array{pais_procedencia: string, numero_activo: string, proveedor: string, costo_usd: float, fecha_adquisicion: string}
     */
    private function fichaTecnica(FormRequest $request): array
    {
        return [
            'pais_procedencia' => $request->validated('pais_procedencia'),
            'numero_activo' => $request->validated('numero_activo'),
            'proveedor' => $request->validated('proveedor'),
            'costo_usd' => (float) $request->validated('costo_usd'),
            'fecha_adquisicion' => $request->validated('fecha_adquisicion'),
        ];
    }
}
