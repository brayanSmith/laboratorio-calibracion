<?php

namespace App\Modules\Objetivos\Policies;

use App\Enums\TenantPermission;
use App\Models\User;

/**
 * El módulo Objetivos se administra con el mismo permiso que los objetivos del dashboard.
 */
class ObjetivoUserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::DashboardObjetivos->value);
    }

    /**
     * Determine whether the user can save or remove the objetivos of the tenant's users.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::DashboardObjetivos->value);
    }
}
