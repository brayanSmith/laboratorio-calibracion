<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_platform_admin;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->is_platform_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_platform_admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->is_platform_admin;
    }

    /**
     * Determine whether the user can manage the users that belong to the tenant.
     */
    public function manageUsers(User $user, Tenant $tenant): bool
    {
        return $user->is_platform_admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->is_platform_admin && $user->tenant_id !== $tenant->id;
    }
}
