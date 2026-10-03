<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Mantenimiento;
use App\Models\User;

class MantenimientoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::MantenimientosVer->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->tenant_id === $mantenimiento->tenant_id
            && $user->can(TenantPermission::MantenimientosEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->tenant_id === $mantenimiento->tenant_id
            && $user->can(TenantPermission::MantenimientosEliminar->value);
    }
}
