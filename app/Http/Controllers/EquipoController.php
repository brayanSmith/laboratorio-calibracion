<?php

namespace App\Http\Controllers;

use App\Http\Requests\Equipos\StoreEquipoRequest;
use App\Http\Requests\Equipos\UpdateEquipoRequest;
use App\Models\Area;
use App\Models\Bahia;
use App\Models\Cliente;
use App\Models\DocumentoEquipo;
use App\Models\Equipo;
use App\Models\EquipoProgramacion;
use App\Models\Fabricante;
use App\Models\TipoEquipo;
use App\Models\TipoMagnitud;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EquipoController extends Controller
{
    /**
     * Display a listing of the tenant's equipos.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Equipo::class);

        $equipos = Equipo::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with([
                'tipoEquipo:id,nombre',
                'fabricante:id,nombre',
                'area:id,nombre',
                'bahia:id,nombre',
                'cliente:id,nombre',
                'equipoEspecificacionTecnica',
                'equipoProgramaciones' => fn ($query) => $query->latest(),
            ])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $equipos = $equipos->through(fn (Equipo $equipo) => $this->conDocumentos($equipo));

        return Inertia::render('equipos/index', [
            'equipos' => $equipos,
            'options' => $this->formOptions($request->user()->tenant_id),
        ]);
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
                    'patron_referencia', 'concatenar_codigo_nombre', 'requiere_programacion', 'cliente_id',
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
            'equipoProgramaciones' => fn ($query) => $query->latest(),
        ]);

        return Inertia::render('equipos/show', [
            'equipo' => $this->conDocumentos($equipo),
            'options' => $this->formOptions($equipo->tenant_id),
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
                    'patron_referencia', 'concatenar_codigo_nombre', 'requiere_programacion', 'cliente_id',
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
     * Serialize the equipo along with its documentos (each including their archivo_url).
     *
     * @return array<string, mixed>
     */
    private function conDocumentos(Equipo $equipo): array
    {
        $documentos = $equipo->equipoDocumentos()
            ->latest()
            ->get()
            ->map(fn (DocumentoEquipo $documento) => [
                'id' => $documento->id,
                'nombre' => $documento->nombre,
                'archivo_url' => $documento->archivoUrl(),
            ]);

        return [
            ...$equipo->toArray(),
            'equipo_documentos' => $documentos,
        ];
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

    /**
     * Get the select options for the equipo form, scoped to the given tenant.
     *
     * @return array{
     *     tipoEquipos: Collection<int, TipoEquipo>,
     *     fabricantes: Collection<int, Fabricante>,
     *     areas: Collection<int, Area>,
     *     bahias: Collection<int, Bahia>,
     *     clientes: Collection<int, Cliente>,
     *     tiposMagnitud: Collection<int, TipoMagnitud>,
     *     unidadesMedida: Collection<int, UnidadMedida>,
     * }
     */
    private function formOptions(int $tenantId): array
    {
        return [
            'tipoEquipos' => TipoEquipo::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'fabricantes' => Fabricante::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'areas' => Area::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'bahias' => Bahia::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre', 'area_id']),
            'clientes' => Cliente::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'tiposMagnitud' => TipoMagnitud::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'unidadesMedida' => UnidadMedida::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre', 'simbolo']),
        ];
    }
}
