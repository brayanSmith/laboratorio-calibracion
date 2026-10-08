<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\MedicionAlcance;
use App\Models\User;

class MedicionAlcancePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::AlcancesMedicionVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::AlcancesMedicionCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MedicionAlcance $medicionAlcance): bool
    {
        return $user->tenant_id === $medicionAlcance->tenant_id
            && $user->can(TenantPermission::AlcancesMedicionEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MedicionAlcance $medicionAlcance): bool
    {
        return $user->tenant_id === $medicionAlcance->tenant_id
            && $user->can(TenantPermission::AlcancesMedicionEliminar->value);
    }
}
