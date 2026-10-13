<?php

namespace App\Http\Controllers\Plataforma;

use App\Actions\Tenants\SetupTenantRoles;
use App\Actions\Usuarios\CreateTenantUser;
use App\Concerns\FiltraListados;
use App\Enums\TenantRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\StoreTenantRequest;
use App\Http\Requests\Tenants\UpdateTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    use FiltraListados;

    /**
     * Display a listing of the tenants, optionally filtered by column (`filtro[nombre]`,
     * `slug`, `usuarios`, `activo`).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Tenant::class);

        $filtros = $this->filtrosDe($request, ['nombre', 'slug', 'usuarios', 'activo']);

        $consulta = Tenant::query()->withCount('users')->latest('id');

        foreach (['nombre', 'slug'] as $columna) {
            if (isset($filtros[$columna])) {
                $this->whereContiene($consulta, $columna, $filtros[$columna]);
            }
        }

        if (isset($filtros['usuarios']) && ctype_digit($filtros['usuarios'])) {
            $consulta->has('users', '=', (int) $filtros['usuarios']);
        }

        if (isset($filtros['activo']) && in_array($filtros['activo'], ['1', '0'], true)) {
            $consulta->where('activo', $filtros['activo'] === '1');
        }

        return Inertia::render('plataforma/tenants/index', [
            'tenants' => $consulta->paginate(15)->withQueryString(),
            'filtros' => (object) $filtros,
        ]);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(StoreTenantRequest $request, CreateTenantUser $createTenantUser, SetupTenantRoles $setupTenantRoles): RedirectResponse
    {
        [$tenant, $admin] = DB::transaction(function () use ($request, $createTenantUser, $setupTenantRoles) {
            $tenant = Tenant::create($request->safe()->only(['nombre', 'slug', 'activo']));

            $admin = $createTenantUser->handle(
                $tenant,
                $request->validated('admin_name'),
                $request->validated('admin_email'),
                $request->validated('admin_password'),
            );

            $setupTenantRoles->handle($tenant, $admin);

            return [$tenant, $admin];
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tenant creado. Entrega la contraseña temporal a :email; deberá cambiarla al iniciar sesión.', ['email' => $admin->email]),
        ]);

        return to_route('plataforma.tenants.edit', $tenant);
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenant $tenant): Response
    {
        Gate::authorize('view', $tenant);

        $tenant->loadCount('users');

        return Inertia::render('plataforma/tenants/show', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * Show the form for editing the specified tenant.
     */
    public function edit(Tenant $tenant): Response
    {
        Gate::authorize('update', $tenant);

        return Inertia::render('plataforma/tenants/edit', [
            'tenant' => $tenant,
            'users' => $tenant->users()
                ->where('is_platform_admin', false)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'must_change_password']),
            'roles' => array_column(TenantRole::cases(), 'value'),
        ]);
    }

    /**
     * Update the specified tenant.
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant actualizado.')]);

        return to_route('plataforma.tenants.edit', $tenant);
    }

    /**
     * Remove the specified tenant.
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
        Gate::authorize('delete', $tenant);

        if ($tenant->users()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar un tenant que tiene usuarios. Desactívalo en su lugar.'),
            ]);

            return back();
        }

        $tenant->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant eliminado.')]);

        return to_route('plataforma.tenants.index');
    }
}
