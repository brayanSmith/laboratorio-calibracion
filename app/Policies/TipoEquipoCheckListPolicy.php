<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\TipoEquipoCheckList;
use App\Models\User;

class TipoEquipoCheckListPolicy
{
    /**
     * Determine whether the user can update the check list item.
     *
     * The check list is managed as part of the tipo de equipo, so it requires the same permission.
     */
    public function update(User $user, TipoEquipoCheckList $tipoEquipoCheckList): bool
    {
        return $this->manages($user, $tipoEquipoCheckList);
    }

    /**
     * Determine whether the user can delete the check list item.
     */
    public function delete(User $user, TipoEquipoCheckList $tipoEquipoCheckList): bool
    {
        return $this->manages($user, $tipoEquipoCheckList);
    }

    private function manages(User $user, TipoEquipoCheckList $tipoEquipoCheckList): bool
    {
        return $user->tenant_id === $tipoEquipoCheckList->tenant_id
            && $user->can(TenantPermission::TiposEquipoEditar->value);
    }
}
