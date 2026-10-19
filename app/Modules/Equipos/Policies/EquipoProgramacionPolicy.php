<?php

namespace App\Modules\Equipos\Policies;

use App\Enums\TenantPermission;
use App\Models\User;
use App\Modules\Equipos\Models\EquipoProgramacion;

class EquipoProgramacionPolicy
{
    /**
     * Determine whether the user can search programaciones de servicio.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::EquiposVer->value);
    }

    /**
     * Determine whether the user can delete the programacion.
     *
     * The programacion is managed as part of the equipo, so it requires the same permission.
     * Once the equipo has arrived (RECIBIDO) the servicio ya se gestionó and it can't be deleted.
     */
    public function delete(User $user, EquipoProgramacion $equipoProgramacion): bool
    {
        return $user->tenant_id === $equipoProgramacion->tenant_id
            && $equipoProgramacion->eliminable
            && $user->can(TenantPermission::EquiposEditar->value);
    }
}
