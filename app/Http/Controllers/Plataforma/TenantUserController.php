<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\ResetTenantUserPasswordRequest;
use App\Http\Requests\Tenants\UpdateTenantUserRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    public function resetPassword(ResetTenantUserPasswordRequest $request, Tenant $tenant, User $user): RedirectResponse
    {
        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Contraseña temporal asignada. Entrégala a :email; deberá cambiarla al iniciar sesión.', ['email' => $user->email]),
        ]);

        return back();
    }
}
