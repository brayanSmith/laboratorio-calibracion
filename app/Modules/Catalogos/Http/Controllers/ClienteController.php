<?php

namespace App\Modules\Catalogos\Http\Controllers;

use App\Concerns\EliminaPorLote;
use App\Enums\TenantPermission;
use App\Enums\TipoDocumentoCliente;
use App\Http\Controllers\Controller;
use App\Modules\Catalogos\Http\Requests\StoreClienteRequest;
use App\Modules\Catalogos\Http\Requests\UpdateClienteRequest;
use App\Modules\Catalogos\Models\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    use EliminaPorLote;

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
            ->get(['id', 'nombre', 'tipo_documento', 'numero_documento', 'email', 'telefono', 'direccion', 'portal_usuario', 'portal_password', 'portal_activo', 'portal_ultimo_acceso'])
            ->map(fn (Cliente $cliente) => [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'tipo_documento' => $cliente->tipo_documento?->value,
                'numero_documento' => $cliente->numero_documento,
                'email' => $cliente->email,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion,
                'equipos_count' => (int) $cliente->getAttribute('equipos_count'),
                // Acceso al portal de clientes (la contraseña nunca sale: solo si tiene una).
                'portal' => [
                    'activo' => $cliente->portal_activo,
                    'usuario' => $cliente->portal_usuario,
                    'tiene_contrasena' => $cliente->portal_password !== null,
                    'ultimo_acceso' => $cliente->portal_ultimo_acceso?->toIso8601String(),
                ],
            ]);

        return Inertia::render('clientes/index', [
            'clientes' => $clientes,
            'tiposDocumento' => array_map(fn (TipoDocumentoCliente $tipo): array => [
                'value' => $tipo->value,
                'label' => $tipo->label(),
            ], TipoDocumentoCliente::cases()),
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

        $motivo = $this->eliminarRegistro($cliente);

        if ($motivo !== null) {
            Inertia::flash('toast', $this->toastNoEliminable($cliente, $motivo));

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente eliminado.')]);

        return to_route('clientes.index');
    }

    protected function modeloPorLote(): string
    {
        return Cliente::class;
    }

    protected function permisoEliminarPorLote(): TenantPermission
    {
        return TenantPermission::ClientesEliminarLote;
    }

    /**
     * Lo que impide eliminar el registro (ver eliminarRegistro()), para el detalle del aviso.
     */
    protected function dependenciasDe(Model $registro): array
    {
        return [
            self::dependencia(__('Equipos'), $registro->equipo(), fn (Model $equipo): string => $equipo->codigo),
            self::dependencia(__('Despachos que recibió'), $registro->despachoRecibido()->with('ordenTrabajo:id,codigo'), fn (Model $dependiente): ?string => $dependiente->ordenTrabajo?->codigo),
            self::dependencia(__('Ingresos que entregó'), $registro->ingresoEntregado(), fn (Model $ingreso): string => $ingreso->codigo),
        ];
    }

    /**
     * Elimina el registro con las reglas de destroy(); también lo usa la eliminación por lote.
     *
     * @param  Cliente  $registro
     */
    protected function eliminarRegistro(Model $registro): ?string
    {
        if ($registro->equipo()->exists() || $registro->despachoRecibido()->exists() || $registro->ingresoEntregado()->exists()) {
            return __('No se puede eliminar un cliente que tiene equipos, ingresos o despachos asociados.');
        }

        $registro->delete();

        return null;
    }
}
