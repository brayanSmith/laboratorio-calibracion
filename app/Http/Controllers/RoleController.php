<?php

namespace App\Http\Controllers;

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display the roles of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);

        $tenantId = $request->user()->tenant_id;

        $usersPerRole = DB::table(config('permission.table_names.model_has_roles'))
            ->where('tenant_id', $tenantId)
            ->select('role_id', DB::raw('count(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $roles = Role::query()
            ->where('tenant_id', $tenantId)
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => TenantRole::tryFrom($role->name)?->isSystem() ?? false,
                'permissions' => $role->permissions->pluck('name')->sort()->values(),
                'users_count' => (int) ($usersPerRole[$role->id] ?? 0),
            ]);

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'catalog' => TenantPermission::catalog(),
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'tenant_id' => $request->user()->tenant_id,
        ]);
        $role->syncPermissions($request->validated('permissions'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rol creado.')]);

        return to_route('roles.index');
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role): Response
    {
        Gate::authorize('update', $role);

        return Inertia::render('roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values(),
            ],
            'catalog' => TenantPermission::catalog(),
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rol actualizado.')]);

        return to_route('roles.index');
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $hasUsers = DB::table(config('permission.table_names.model_has_roles'))
            ->where('tenant_id', $role->tenant_id)
            ->where('role_id', $role->id)
            ->exists();

        if ($hasUsers) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No se puede eliminar un rol que tiene usuarios asignados. Reasígnalos primero.'),
            ]);

            return back();
        }

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rol eliminado.')]);

        return to_route('roles.index');
    }
}
