<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\UnidadMedida;
use App\Models\User;

class UnidadMedidaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::UnidadesMedidaVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::UnidadesMedidaCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UnidadMedida $unidadMedida): bool
    {
        return $user->tenant_id === $unidadMedida->tenant_id
            && $user->can(TenantPermission::UnidadesMedidaEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UnidadMedida $unidadMedida): bool
    {
        return $user->tenant_id === $unidadMedida->tenant_id
            && $user->can(TenantPermission::UnidadesMedidaEliminar->value);
    }
}
