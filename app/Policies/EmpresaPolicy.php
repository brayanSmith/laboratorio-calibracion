<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Empresa;
use App\Models\User;

class EmpresaPolicy
{
    /**
     * Determine whether the user can view the empresa of their tenant.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::EmpresaVer->value);
    }

    /**
     * Determine whether the user can register the empresa of their tenant.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::EmpresaCrear->value);
    }

    /**
     * Determine whether the user can update the empresa.
     */
    public function update(User $user, Empresa $empresa): bool
    {
        return $user->tenant_id === $empresa->tenant_id
            && $user->can(TenantPermission::EmpresaEditar->value);
    }

    /**
     * Determine whether the user can delete the empresa.
     */
    public function delete(User $user, Empresa $empresa): bool
    {
        return $user->tenant_id === $empresa->tenant_id
            && $user->can(TenantPermission::EmpresaEliminar->value);
    }
}
