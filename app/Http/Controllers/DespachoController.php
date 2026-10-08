<?php

namespace App\Http\Controllers;

use App\Http\Requests\Despachos\UpdateDespachoRequest;
use App\Models\Cliente;
use App\Models\Despacho;
use App\Models\Novedad;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DespachoController extends Controller
{
    /**
     * Display the despachos of the tenant. No "crear": un despacho solo se origina al
     * finalizar una calibración (ver CalibracionController::finalizar()).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Despacho::class);

        $tenantId = $request->user()->tenant_id;

        $despachos = Despacho::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'ordenTrabajo:id,codigo,equipo_programacion_id',
                'ordenTrabajo.equipoProgramacion:id,equipo_id',
                'ordenTrabajo.equipoProgramacion.equipo:id,codigo,modelo,cliente_id,tipo_equipo_id',
                'ordenTrabajo.equipoProgramacion.equipo.cliente:id,nombre',
                'ordenTrabajo.equipoProgramacion.equipo.tipoEquipo:id,nombre',
                'tecnicoEntrega:id,name',
                'clienteRecibe:id,nombre',
                'novedad:id,nombre',
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Despacho $despacho) => [
                'id' => $despacho->id,
                'orden_trabajo_id' => $despacho->orden_trabajo_id,
                'orden_trabajo_codigo' => $despacho->ordenTrabajo->codigo,
                'tecnico_entrega_id' => $despacho->tecnico_entrega_id,
                'tecnico_entrega_nombre' => $despacho->tecnicoEntrega?->name,
                'entrega_autorizada' => $despacho->entrega_autorizada,
                'cliente_recibe_id' => $despacho->cliente_recibe_id,
                'cliente_recibe_nombre' => $despacho->clienteRecibe?->nombre,
                'firma_url' => $despacho->firmaUrl(),
                'entrega_recibida' => $despacho->entrega_recibida,
                'novedad_id' => $despacho->novedad_id,
                'novedad_nombre' => $despacho->novedad?->nombre,
                'equipo' => [
                    'codigo' => $despacho->ordenTrabajo->equipoProgramacion->equipo->codigo,
                    'modelo' => $despacho->ordenTrabajo->equipoProgramacion->equipo->modelo,
                    'tipo_equipo' => ['nombre' => $despacho->ordenTrabajo->equipoProgramacion->equipo->tipoEquipo->nombre],
                    'cliente' => ['nombre' => $despacho->ordenTrabajo->equipoProgramacion->equipo->cliente->nombre],
                ],
            ]);

        return Inertia::render('despachos/index', [
            'despachos' => $despachos,
            'tecnicos' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name as nombre']),
            'clientes' => Cliente::query()->where('tenant_id', $tenantId)->orderBy('nombre')->get(['id', 'nombre']),
            'novedadesDespacho' => Novedad::query()->where('tenant_id', $tenantId)->where('categoria', 'SALIDA')->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /**
     * Update the specified despacho.
     */
    public function update(UpdateDespachoRequest $request, Despacho $despacho): RedirectResponse
    {
        $data = $request->safe()->except(['firma_cliente_recibe', 'eliminar_firma']);

        if ($request->hasFile('firma_cliente_recibe')) {
            $this->deleteFirma($despacho);
            $data['firma_cliente_recibe'] = $request->file('firma_cliente_recibe')->store("firmas/{$despacho->tenant_id}", 'public');
        } elseif ($request->boolean('eliminar_firma')) {
            $this->deleteFirma($despacho);
            $data['firma_cliente_recibe'] = null;
        }

        $despacho->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Despacho actualizado.')]);

        return to_route('despachos.index');
    }

    /**
     * Remove the specified despacho.
     */
    public function destroy(Despacho $despacho): RedirectResponse
    {
        Gate::authorize('delete', $despacho);

        $this->deleteFirma($despacho);
        $despacho->update(['firma_cliente_recibe' => null]);
        $despacho->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Despacho eliminado.')]);

        return to_route('despachos.index');
    }

    /**
     * Delete the firma_cliente_recibe file of the despacho, if it has one.
     */
    private function deleteFirma(Despacho $despacho): void
    {
        if ($despacho->firma_cliente_recibe) {
            Storage::disk('public')->delete($despacho->firma_cliente_recibe);
        }
    }
}
