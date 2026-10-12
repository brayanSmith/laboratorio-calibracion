<?php

namespace App\Http\Controllers\Plataforma;

use App\Actions\Tenants\SetupTenantRoles;
use App\Actions\Usuarios\AssignTemporaryPassword;
use App\Actions\Usuarios\CreateTenantUser;
use App\Enums\TenantRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\ResetTenantUserPasswordRequest;
use App\Http\Requests\Tenants\StoreTenantUserRequest;
use App\Http\Requests\Tenants\UpdateTenantUserRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TenantUserController extends Controller
{
    /**
     * Create a user in the tenant with one of its default roles and a temporary password.
     */
    public function store(StoreTenantUserRequest $request, Tenant $tenant, CreateTenantUser $createTenantUser, SetupTenantRoles $setupTenantRoles): RedirectResponse
    {
        $user = DB::transaction(function () use ($request, $tenant, $createTenantUser, $setupTenantRoles) {
            $setupTenantRoles->handle($tenant);

            $user = $createTenantUser->handle(
                $tenant,
                $request->validated('name'),
                $request->validated('email'),
                $request->validated('password'),
            );

            $setupTenantRoles->assignRole($tenant, $user, TenantRole::from($request->validated('role')));

            return $user;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Usuario creado. Entrega la contraseña temporal a :email; deberá cambiarla al iniciar sesión.', ['email' => $user->email]),
        ]);

        return back();
    }

    /**
     * Update the name and email of a user of the tenant.
     */
    public function update(UpdateTenantUserRequest $request, Tenant $tenant, User $user): RedirectResponse
    {
        $user->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usuario actualizado.')]);

        return back();
    }

    /**
     * Assign a new temporary password to a user of the tenant and close their sessions.
     */
    public function resetPassword(ResetTenantUserPasswordRequest $request, Tenant $tenant, User $user, AssignTemporaryPassword $assignTemporaryPassword): RedirectResponse
    {
        $assignTemporaryPassword->handle($user, $request->validated('password'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Contraseña temporal asignada. Entrégala a :email; deberá cambiarla al iniciar sesión.', ['email' => $user->email]),
        ]);

        return back();
    }
}
