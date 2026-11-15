<?php

namespace App\Modules\Recordatorios\Policies;

use App\Enums\TenantPermission;
use App\Models\User;
use App\Modules\Recordatorios\Models\RecordatorioPlantilla;

class RecordatorioPlantillaPolicy
{
    /**
     * Determine whether the user can create plantillas for the correos of their tenant.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::RecordatoriosEditar->value);
    }

    /**
     * Determine whether the user can edit or put in use the plantilla.
     */
    public function update(User $user, RecordatorioPlantilla $plantilla): bool
    {
        return $user->tenant_id === $plantilla->tenant_id
            && $user->can(TenantPermission::RecordatoriosEditar->value);
    }

    /**
     * Determine whether the user can delete the plantilla.
     */
    public function delete(User $user, RecordatorioPlantilla $plantilla): bool
    {
        return $this->update($user, $plantilla);
    }
}
