<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::ItemsVer->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::ItemsCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Item $item): bool
    {
        return $user->tenant_id === $item->tenant_id
            && $user->can(TenantPermission::ItemsEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Item $item): bool
    {
        return $user->tenant_id === $item->tenant_id
            && $user->can(TenantPermission::ItemsEliminar->value);
    }
}
