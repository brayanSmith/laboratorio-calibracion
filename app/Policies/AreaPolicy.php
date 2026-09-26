<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Area;
use App\Models\User;

class AreaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::AreasVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::AreasCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Area $area): bool
    {
        return $user->tenant_id === $area->tenant_id
            && $user->can(TenantPermission::AreasEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Area $area): bool
    {
        return $user->tenant_id === $area->tenant_id
            && $user->can(TenantPermission::AreasEliminar->value);
    }
}
