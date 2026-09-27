<?php

namespace App\Http\Controllers;

use App\Http\Requests\Clientes\StoreClienteRequest;
use App\Http\Requests\Clientes\UpdateClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    /**
     * Display the clientes of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Cliente::class);

        $clientes = Cliente::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount('equipo as equipos_count')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'email', 'telefono', 'direccion'])
            ->map(fn (Cliente $cliente) => [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'email' => $cliente->email,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion,
                'equipos_count' => (int) $cliente->getAttribute('equipos_count'),
            ]);

        return Inertia::render('clientes/index', [
            'clientes' => $clientes,
        ]);
    }

    /**
     * Store a newly created cliente.
     */
    public function store(StoreClienteRequest $request): RedirectResponse
    {
        Cliente::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente creado.')]);

        return to_route('clientes.index');
    }

    /**
     * Update the specified cliente.
     */
    public function update(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente actualizado.')]);

        return to_route('clientes.index');
    }

    /**
     * Remove the specified cliente.
     */
    public function destroy(Cliente $cliente): RedirectResponse
    {
        Gate::authorize('delete', $cliente);

        $enUso = $cliente->equipo()->exists()
            || $cliente->despachoRecibido()->exists()
            || $cliente->ingresoEntregado()->exists();

        if ($enUso) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar un cliente que tiene equipos, ingresos o despachos asociados.'),
            ]);

            return back();
        }

        $cliente->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente eliminado.')]);

        return to_route('clientes.index');
    }
}
