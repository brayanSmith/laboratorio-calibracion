<?php

namespace App\Modules\Catalogos\Policies;

use App\Enums\TenantPermission;
use App\Models\User;
use App\Modules\Catalogos\Models\TipoEquipoCheckList;

class TipoEquipoCheckListPolicy
{
    /**
     * Determine whether the user can update the check list item.
     *
     * The check list has its own permission, separate from editing the tipo de equipo.
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
            && $user->can(TenantPermission::TiposEquipoChecklist->value);
    }
}
