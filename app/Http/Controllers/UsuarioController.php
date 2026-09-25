<?php

namespace App\Http\Controllers;

use App\Actions\Usuarios\AssignTemporaryPassword;
use App\Actions\Usuarios\CreateTenantUser;
use App\Enums\TenantRole;
use App\Http\Requests\Usuarios\ResetUsuarioPasswordRequest;
use App\Http\Requests\Usuarios\StoreUsuarioRequest;
use App\Http\Requests\Usuarios\UpdateUsuarioRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    /**
     * Display the users of the tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();

        $usuarios = User::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('is_platform_admin', false)
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (User $usuario) => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'must_change_password' => $usuario->must_change_password,
                'role_id' => $usuario->roles->first()?->id,
                'role_name' => $usuario->roles->first()?->name,
                'can_manage' => Gate::allows('update', $usuario),
            ]);

        $roles = Role::query()
            ->where('tenant_id', $actor->tenant_id)
            ->when(
                ! $actor->hasRole(TenantRole::Administrador->value),
                fn ($query) => $query->where('name', '!=', TenantRole::Administrador->value),
            )
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('usuarios/index', [
            'usuarios' => $usuarios,
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created user in the tenant.
     */
    public function store(StoreUsuarioRequest $request, CreateTenantUser $createTenantUser): RedirectResponse
    {
        $usuario = DB::transaction(function () use ($request, $createTenantUser) {
            $usuario = $createTenantUser->handle(
                $request->user()->tenant,
                $request->validated('name'),
                $request->validated('email'),
                $request->validated('password'),
            );

            $usuario->syncRoles(Role::findOrFail($request->validated('role_id')));

            return $usuario;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Usuario creado. Entrega la contraseña temporal a :email; deberá cambiarla al iniciar sesión.', ['email' => $usuario->email]),
        ]);

        return to_route('usuarios.index');
    }

    /**
     * Update the name, email and role of a user of the tenant.
     */
    public function update(UpdateUsuarioRequest $request, User $usuario): RedirectResponse
    {
        $usuario->update($request->safe()->only(['name', 'email']));
        $usuario->syncRoles(Role::findOrFail($request->validated('role_id')));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usuario actualizado.')]);

        return to_route('usuarios.index');
    }

    /**
     * Assign a new temporary password to a user of the tenant.
     */
    public function resetPassword(ResetUsuarioPasswordRequest $request, User $usuario, AssignTemporaryPassword $assignTemporaryPassword): RedirectResponse
    {
        $assignTemporaryPassword->handle($usuario, $request->validated('password'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Contraseña temporal asignada. Entrégala a :email; deberá cambiarla al iniciar sesión.', ['email' => $usuario->email]),
        ]);

        return back();
    }
}
