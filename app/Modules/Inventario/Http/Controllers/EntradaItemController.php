<?php

namespace App\Modules\Inventario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventario\Http\Requests\RecibirEntradaItemRequest;
use App\Modules\Inventario\Http\Requests\StoreEntradaItemRequest;
use App\Modules\Inventario\Models\EntradaItem;
use App\Modules\Inventario\Models\Item;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EntradaItemController extends Controller
{
    /**
     * Store a newly created entrada for the given item.
     */
    public function store(StoreEntradaItemRequest $request, Item $item): RedirectResponse
    {
        $datos = $request->validated();

        $item->entradas()->create([
            ...$datos,
            'fecha_recibido' => $datos['recibido'] ? $datos['fecha_recibido'] : null,
            'precio_total' => round((float) $datos['cantidad'] * (float) $datos['precio_unitario'], 2),
            'tenant_id' => $item->tenant_id,
        ]);

        if ($datos['recibido']) {
            $item->actualizarPrecioDesdeUltimaEntrada();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entrada registrada.')]);

        return to_route('items.index');
    }

    /**
     * Mark a pending entrada as received.
     */
    public function recibir(RecibirEntradaItemRequest $request, EntradaItem $entradaItem): RedirectResponse
    {
        if ($entradaItem->recibido) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Esta entrada ya fue recibida.')]);

            return back();
        }

        $entradaItem->update([
            'recibido' => true,
            'fecha_recibido' => $request->validated('fecha_recibido'),
        ]);

        $entradaItem->item->actualizarPrecioDesdeUltimaEntrada();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entrada marcada como recibida.')]);

        return to_route('items.index');
    }
}
