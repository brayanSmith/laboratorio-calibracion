<?php

namespace App\Http\Controllers;

use App\Http\Requests\Equipos\StoreEquipoRequest;
use App\Http\Requests\Equipos\UpdateEquipoRequest;
use App\Models\Area;
use App\Models\Bahia;
use App\Models\Cliente;
use App\Models\Equipo;
use App\Models\Fabricante;
use App\Models\TipoEquipo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EquipoController extends Controller
{
    /**
     * Display a listing of the tenant's equipos.
     */
    public function index(Request $request): Response
    {
        $equipos = Equipo::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with([
                'tipoEquipo:id,nombre',
                'fabricante:id,nombre',
                'area:id,nombre',
                'bahia:id,nombre',
                'cliente:id,nombre',
            ])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('equipos/index', [
            'equipos' => $equipos,
            'options' => $this->formOptions($request->user()->tenant_id),
        ]);
    }

    /**
     * Store a newly created equipo.
     */
    public function store(StoreEquipoRequest $request): RedirectResponse
    {
        $equipo = Equipo::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Equipo creado.')]);

        return to_route('equipos.edit', $equipo);
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
        ]);

        return Inertia::render('equipos/show', [
            'equipo' => $equipo,
        ]);
    }

    /**
     * Show the form for editing the specified equipo.
     */
    public function edit(Request $request, Equipo $equipo): Response
    {
        Gate::authorize('update', $equipo);

        return Inertia::render('equipos/edit', [
            'equipo' => $equipo,
            'options' => $this->formOptions($equipo->tenant_id),
        ]);
    }

    /**
     * Update the specified equipo.
     */
    public function update(UpdateEquipoRequest $request, Equipo $equipo): RedirectResponse
    {
        $equipo->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Equipo actualizado.')]);

        return to_route('equipos.edit', $equipo);
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
     * Get the select options for the equipo form, scoped to the given tenant.
     *
     * @return array{
     *     tipoEquipos: Collection<int, TipoEquipo>,
     *     fabricantes: Collection<int, Fabricante>,
     *     areas: Collection<int, Area>,
     *     bahias: Collection<int, Bahia>,
     *     clientes: Collection<int, Cliente>,
     * }
     */
    private function formOptions(int $tenantId): array
    {
        return [
            'tipoEquipos' => TipoEquipo::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'fabricantes' => Fabricante::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'areas' => Area::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'bahias' => Bahia::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'clientes' => Cliente::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }
}
