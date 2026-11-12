<?php

namespace App\Modules\Recordatorios\Policies;

use App\Enums\TenantPermission;
use App\Models\User;

class RecordatorioPolicy
{
    /**
     * Determine whether the user can view the recordatorio configuration of their tenant.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::RecordatoriosVer->value);
    }

    /**
     * Determine whether the user can change the recordatorio configuration and send a test.
     */
    public function update(User $user): bool
    {
        return $user->can(TenantPermission::RecordatoriosEditar->value);
    }
}
