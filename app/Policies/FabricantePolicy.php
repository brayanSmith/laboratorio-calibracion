<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Fabricante;
use App\Models\User;

class FabricantePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::FabricantesVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::FabricantesCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Fabricante $fabricante): bool
    {
        return $user->tenant_id === $fabricante->tenant_id
            && $user->can(TenantPermission::FabricantesEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Fabricante $fabricante): bool
    {
        return $user->tenant_id === $fabricante->tenant_id
            && $user->can(TenantPermission::FabricantesEliminar->value);
    }
}
