<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Ingreso;
use App\Models\User;

class IngresoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::IngresosVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::IngresosCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Ingreso $ingreso): bool
    {
        return $user->tenant_id === $ingreso->tenant_id
            && $user->can(TenantPermission::IngresosEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Ingreso $ingreso): bool
    {
        return $user->tenant_id === $ingreso->tenant_id
            && $user->can(TenantPermission::IngresosEliminar->value);
    }
}
