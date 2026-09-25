<?php

namespace App\Http\Controllers\Plataforma;

use App\Actions\Usuarios\AssignTemporaryPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\ResetTenantUserPasswordRequest;
use App\Http\Requests\Tenants\UpdateTenantUserRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TenantUserController extends Controller
{
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
