<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Despacho;
use App\Models\User;

class DespachoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::DespachosVer->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Despacho $despacho): bool
    {
        return $user->tenant_id === $despacho->tenant_id
            && $user->can(TenantPermission::DespachosEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Despacho $despacho): bool
    {
        return $user->tenant_id === $despacho->tenant_id
            && $user->can(TenantPermission::DespachosEliminar->value);
    }
}
