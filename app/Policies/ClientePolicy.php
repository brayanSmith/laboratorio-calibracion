<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Cliente;
use App\Models\User;

class ClientePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::ClientesVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::ClientesCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Cliente $cliente): bool
    {
        return $user->tenant_id === $cliente->tenant_id
            && $user->can(TenantPermission::ClientesEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->tenant_id === $cliente->tenant_id
            && $user->can(TenantPermission::ClientesEliminar->value);
    }
}
