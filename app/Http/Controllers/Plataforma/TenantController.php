<?php

namespace App\Http\Controllers\Plataforma;

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\StoreTenantRequest;
use App\Http\Requests\Tenants\UpdateTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    /**
     * Display a listing of the tenants.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Tenant::class);

        $tenants = Tenant::query()
            ->withCount('users')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('plataforma/tenants/index', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(StoreTenantRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        [$tenant, $admin] = DB::transaction(function () use ($request, $createTeam) {
            $tenant = Tenant::create($request->safe()->only(['nombre', 'slug', 'activo']));

            $admin = new User([
                'name' => $request->validated('admin_name'),
                'email' => $request->validated('admin_email'),
                'password' => $request->validated('admin_password'),
            ]);
            $admin->tenant_id = $tenant->id;
            $admin->must_change_password = true;
            $admin->save();

            $createTeam->handle($admin, $admin->name."'s Team", isPersonal: true);

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
