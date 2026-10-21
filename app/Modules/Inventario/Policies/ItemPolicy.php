<?php

namespace App\Modules\Inventario\Policies;

use App\Enums\TenantPermission;
use App\Models\User;
use App\Modules\Inventario\Models\Item;

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
     * Determine whether the user can register an entrada for the item.
     */
    public function registrarEntrada(User $user, Item $item): bool
    {
        return $user->tenant_id === $item->tenant_id
            && $user->can(TenantPermission::ItemsRegistrarEntrada->value);
    }

    /**
     * Determine whether the user can view the entradas and salidas of the item.
     */
    public function verEntradas(User $user, Item $item): bool
    {
        return $user->tenant_id === $item->tenant_id
            && $user->can(TenantPermission::ItemsVerEntradas->value);
    }

    /**
     * Determine whether the user can mark the pending entradas of the item as received.
     */
    public function recibirEntradas(User $user, Item $item): bool
    {
        return $user->tenant_id === $item->tenant_id
            && $user->can(TenantPermission::ItemsRecibirEntradas->value);
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
