<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Novedad;
use App\Models\User;

class NovedadPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::NovedadesVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::NovedadesCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Novedad $novedad): bool
    {
        return $user->tenant_id === $novedad->tenant_id
            && $user->can(TenantPermission::NovedadesEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Novedad $novedad): bool
    {
        return $user->tenant_id === $novedad->tenant_id
            && $user->can(TenantPermission::NovedadesEliminar->value);
    }
}
