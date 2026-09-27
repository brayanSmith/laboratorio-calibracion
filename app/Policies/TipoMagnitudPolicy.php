<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\TipoMagnitud;
use App\Models\User;

class TipoMagnitudPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::TiposMagnitudVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::TiposMagnitudCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TipoMagnitud $tipoMagnitud): bool
    {
        return $user->tenant_id === $tipoMagnitud->tenant_id
            && $user->can(TenantPermission::TiposMagnitudEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TipoMagnitud $tipoMagnitud): bool
    {
        return $user->tenant_id === $tipoMagnitud->tenant_id
            && $user->can(TenantPermission::TiposMagnitudEliminar->value);
    }
}
