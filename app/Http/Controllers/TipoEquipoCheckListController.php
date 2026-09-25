<?php

namespace App\Http\Controllers;

use App\Http\Requests\TiposEquipo\StoreTipoEquipoCheckListRequest;
use App\Http\Requests\TiposEquipo\UpdateTipoEquipoCheckListRequest;
use App\Models\TipoEquipo;
use App\Models\TipoEquipoCheckList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TipoEquipoCheckListController extends Controller
{
    /**
     * Store one or many check list items for the tipo de equipo in a single request.
     */
    public function store(StoreTipoEquipoCheckListRequest $request, TipoEquipo $tipoEquipo): RedirectResponse
    {
        /** @var array<int, string> $nombres */
        $nombres = $request->validated('nombres');

        DB::transaction(fn () => $tipoEquipo->tipoEquipoCheckList()->createMany(
            array_map(fn (string $nombre) => [
                'nombre' => $nombre,
                'tenant_id' => $tipoEquipo->tenant_id,
            ], $nombres),
        ));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(':count ítem agregado al checklist.|:count ítems agregados al checklist.', count($nombres)),
        ]);

        return back();
    }

    /**
     * Update the specified check list item.
     */
    public function update(UpdateTipoEquipoCheckListRequest $request, TipoEquipoCheckList $tipoEquipoCheckList): RedirectResponse
    {
        $tipoEquipoCheckList->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ítem del checklist actualizado.')]);

        return back();
    }

    /**
     * Remove the specified check list item.
     */
    public function destroy(TipoEquipoCheckList $tipoEquipoCheckList): RedirectResponse
    {
        Gate::authorize('delete', $tipoEquipoCheckList);

        $tipoEquipoCheckList->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ítem eliminado del checklist.')]);

        return back();
    }
}
