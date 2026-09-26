<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\EmpresaTercero;
use App\Models\User;

class EmpresaTerceroPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::EmpresasTercerasVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::EmpresasTercerasCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EmpresaTercero $empresaTercero): bool
    {
        return $user->tenant_id === $empresaTercero->tenant_id
            && $user->can(TenantPermission::EmpresasTercerasEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmpresaTercero $empresaTercero): bool
    {
        return $user->tenant_id === $empresaTercero->tenant_id
            && $user->can(TenantPermission::EmpresasTercerasEliminar->value);
    }
}
