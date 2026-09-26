<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Laboratorio;
use App\Models\User;

class LaboratorioPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::LaboratoriosVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::LaboratoriosCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Laboratorio $laboratorio): bool
    {
        return $user->tenant_id === $laboratorio->tenant_id
            && $user->can(TenantPermission::LaboratoriosEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Laboratorio $laboratorio): bool
    {
        return $user->tenant_id === $laboratorio->tenant_id
            && $user->can(TenantPermission::LaboratoriosEliminar->value);
    }
}
