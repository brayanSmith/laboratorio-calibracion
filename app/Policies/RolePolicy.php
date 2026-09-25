<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Determine whether the user can view the tenant's roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::RolesGestionar->value);
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::RolesGestionar->value);
    }

    /**
     * Determine whether the user can update the role. The Administrador role is fixed by the platform.
     */
    public function update(User $user, Role $role): bool
    {
        return $this->manages($user, $role) && ! $this->isSystem($role);
    }

    /**
     * Determine whether the user can delete the role. The Administrador role is fixed by the platform.
     */
    public function delete(User $user, Role $role): bool
    {
        return $this->manages($user, $role) && ! $this->isSystem($role);
    }

    private function manages(User $user, Role $role): bool
    {
        return $user->tenant_id !== null
            && $user->tenant_id === $role->tenant_id
            && $user->can(TenantPermission::RolesGestionar->value);
    }

    private function isSystem(Role $role): bool
    {
        return TenantRole::tryFrom($role->name)?->isSystem() ?? false;
    }
}
