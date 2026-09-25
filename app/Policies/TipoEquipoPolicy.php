<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\TipoEquipo;
use App\Models\User;

class TipoEquipoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::TiposEquipoVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::TiposEquipoCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TipoEquipo $tipoEquipo): bool
    {
        return $user->tenant_id === $tipoEquipo->tenant_id
            && $user->can(TenantPermission::TiposEquipoEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TipoEquipo $tipoEquipo): bool
    {
        return $user->tenant_id === $tipoEquipo->tenant_id
            && $user->can(TenantPermission::TiposEquipoEliminar->value);
    }
}
