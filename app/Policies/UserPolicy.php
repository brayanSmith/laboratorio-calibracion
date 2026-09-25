<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can list the tenant's users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::UsuariosGestionar->value);
    }

    /**
     * Determine whether the user can create users in their tenant.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::UsuariosGestionar->value);
    }

    /**
     * Determine whether the user can edit another user of the tenant.
     */
    public function update(User $user, User $target): bool
    {
        return $this->manages($user, $target);
    }

    /**
     * Determine whether the user can replace another user's password.
     */
    public function resetPassword(User $user, User $target): bool
    {
        return $this->manages($user, $target);
    }

    /**
     * Only users with the Administrador role may manage other Administradores,
     * otherwise anyone with this permission could take over an administrator account.
     */
    private function manages(User $user, User $target): bool
    {
        return $user->tenant_id !== null
            && $user->tenant_id === $target->tenant_id
            && ! $target->is_platform_admin
            && $user->can(TenantPermission::UsuariosGestionar->value)
            && ($user->hasRole(TenantRole::Administrador->value) || ! $target->hasRole(TenantRole::Administrador->value));
    }
}
