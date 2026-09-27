<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\ProcedimientoCalibracion;
use App\Models\User;

class ProcedimientoCalibracionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::ProcedimientosCalibracionVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::ProcedimientosCalibracionCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProcedimientoCalibracion $procedimientoCalibracion): bool
    {
        return $user->tenant_id === $procedimientoCalibracion->tenant_id
            && $user->can(TenantPermission::ProcedimientosCalibracionEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProcedimientoCalibracion $procedimientoCalibracion): bool
    {
        return $user->tenant_id === $procedimientoCalibracion->tenant_id
            && $user->can(TenantPermission::ProcedimientosCalibracionEliminar->value);
    }
}
