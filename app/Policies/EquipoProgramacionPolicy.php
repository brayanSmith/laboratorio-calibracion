<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\EquipoProgramacion;
use App\Models\User;

class EquipoProgramacionPolicy
{
    /**
     * Determine whether the user can delete the programacion.
     *
     * The programacion is managed as part of the equipo, so it requires the same permission.
     */
    public function delete(User $user, EquipoProgramacion $equipoProgramacion): bool
    {
        return $user->tenant_id === $equipoProgramacion->tenant_id
            && $user->can(TenantPermission::EquiposEditar->value);
    }
}
