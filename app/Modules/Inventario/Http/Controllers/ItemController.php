<?php

namespace App\Modules\Inventario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventario\Http\Requests\StoreItemRequest;
use App\Modules\Inventario\Http\Requests\UpdateItemRequest;
use App\Modules\Inventario\Models\EntradaItem;
use App\Modules\Inventario\Models\Item;
use App\Modules\Mantenimientos\Models\ItemMantenimiento;
use Illuminate\Http\JsonResponse;
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
            ->conMovimientos()
            ->with(['entradas' => fn ($query) => $query->where('recibido', false)->orderBy('id')])
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'descripcion', 'ubicacion', 'precio_unitario'])
            ->map(function (Item $item) {
                return [
                    'id' => $item->id,
                    'codigo' => $item->codigo,
                    'nombre' => $item->nombre,
                    'descripcion' => $item->descripcion,
                    'ubicacion' => $item->ubicacion,
                    'precio_unitario' => $item->precio_unitario === null ? null : (float) $item->precio_unitario,
                    'entradas' => (float) $item->getAttribute('entradas_recibidas'),
                    'salidas' => (float) $item->getAttribute('salidas_total'),
                    'stock' => $item->stock,
                    'entradas_pendientes' => $item->entradas->map(fn (EntradaItem $entrada) => [
                        'id' => $entrada->id,
                        'cantidad' => (float) $entrada->cantidad,
                        'precio_unitario' => (float) $entrada->precio_unitario,
                        'precio_total' => (float) $entrada->precio_total,
                        'codigo_compra' => $entrada->codigo_compra,
                    ])->values(),
                ];
            });

        return Inertia::render('items/index', [
            'items' => $items,
        ]);
    }

    /**
     * List the entradas and salidas that make up the stock of the item.
     */
    public function movimientos(Item $item): JsonResponse
    {
        Gate::authorize('verEntradas', $item);

        $entradas = $item->entradas()
            ->orderByDesc('id')
            ->get()
            ->map(fn (EntradaItem $entrada) => [
                'id' => $entrada->id,
                'fecha_registro' => $entrada->created_at?->toDateString(),
                'cantidad' => (float) $entrada->cantidad,
                'precio_unitario' => (float) $entrada->precio_unitario,
                'precio_total' => (float) $entrada->precio_total,
                'codigo_compra' => $entrada->codigo_compra,
                'recibido' => $entrada->recibido,
                'fecha_recibido' => $entrada->fecha_recibido?->toDateString(),
                'observaciones' => $entrada->observaciones,
            ]);

        $salidas = $item->itemsMantenimiento()
            ->where('con_stock', true)
            ->with('mantenimiento.ordenTrabajo:id,codigo')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ItemMantenimiento $salida) => [
                'id' => $salida->id,
                'cantidad' => (float) $salida->cantidad,
                'orden_trabajo_codigo' => $salida->mantenimiento?->ordenTrabajo?->codigo,
                'tipo_mantenimiento' => $salida->mantenimiento?->tipo_mantenimiento,
                'estado_mantenimiento' => $salida->mantenimiento?->estado_mantenimiento,
                'fecha' => $salida->mantenimiento?->fecha_mantenimiento?->toDateString()
                    ?? $salida->created_at?->toDateString(),
            ]);

        return response()->json([
            'entradas' => $entradas,
            'salidas' => $salidas,
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
