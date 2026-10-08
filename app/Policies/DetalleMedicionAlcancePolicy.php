<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\DetalleMedicionAlcance;
use App\Models\User;

class DetalleMedicionAlcancePolicy
{
    /**
     * Determine whether the user can update the detalle.
     *
     * The detalles are managed as part of the medición de alcance, so they require the same permission.
     */
    public function update(User $user, DetalleMedicionAlcance $detalleMedicionAlcance): bool
    {
        return $this->manages($user, $detalleMedicionAlcance);
    }

    /**
     * Determine whether the user can delete the detalle.
     */
    public function delete(User $user, DetalleMedicionAlcance $detalleMedicionAlcance): bool
    {
        return $this->manages($user, $detalleMedicionAlcance);
    }

    private function manages(User $user, DetalleMedicionAlcance $detalleMedicionAlcance): bool
    {
        return $user->tenant_id === $detalleMedicionAlcance->tenant_id
            && $user->can(TenantPermission::AlcancesMedicionEditar->value);
    }
}
