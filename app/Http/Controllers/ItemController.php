<?php

namespace App\Http\Controllers;

use App\Http\Requests\Items\StoreItemRequest;
use App\Http\Requests\Items\UpdateItemRequest;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    /**
     * Display the items of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Item::class);

        $items = Item::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount('itemsMantenimiento as usos_count')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'descripcion'])
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'codigo' => $item->codigo,
                'nombre' => $item->nombre,
                'descripcion' => $item->descripcion,
                'usos_count' => (int) $item->getAttribute('usos_count'),
            ]);

        return Inertia::render('items/index', [
            'items' => $items,
        ]);
    }

    /**
     * Store a newly created item.
     */
    public function store(StoreItemRequest $request): RedirectResponse
    {
        Item::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ítem creado.')]);

        return to_route('items.index');
    }

    /**
     * Update the specified item.
     */
    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $item->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ítem actualizado.')]);

        return to_route('items.index');
    }

    /**
     * Remove the specified item.
     */
    public function destroy(Item $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        if ($item->itemsMantenimiento()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar un ítem que ya se usó en mantenimientos.'),
            ]);

            return back();
        }

        $item->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ítem eliminado.')]);

        return to_route('items.index');
    }
}
