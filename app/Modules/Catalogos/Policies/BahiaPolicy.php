<?php

namespace App\Modules\Catalogos\Policies;

use App\Enums\TenantPermission;
use App\Models\User;
use App\Modules\Catalogos\Models\Bahia;

class BahiaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::BahiasVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::BahiasCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Bahia $bahia): bool
    {
        return $user->tenant_id === $bahia->tenant_id
            && $user->can(TenantPermission::BahiasEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Bahia $bahia): bool
    {
        return $user->tenant_id === $bahia->tenant_id
            && $user->can(TenantPermission::BahiasEliminar->value);
    }
}
