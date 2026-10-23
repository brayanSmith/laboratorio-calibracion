<?php

namespace App\Modules\Equipos\Policies;

use App\Enums\TenantPermission;
use App\Models\User;
use App\Modules\Equipos\Models\Equipo;

class EquipoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::EquiposVer->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Equipo $equipo): bool
    {
        return $user->tenant_id === $equipo->tenant_id
            && $user->can(TenantPermission::EquiposVer->value);
    }

    /**
     * Determine whether the user can view the historial de servicios of the model.
     */
    public function verHistorial(User $user, Equipo $equipo): bool
    {
        return $user->tenant_id === $equipo->tenant_id
            && $user->can(TenantPermission::EquiposHistorial->value);
    }

    /**
     * Determine whether the user can add a servicio histórico to the model by hand.
     */
    public function agregarHistorico(User $user, Equipo $equipo): bool
    {
        return $user->tenant_id === $equipo->tenant_id
            && $user->can(TenantPermission::EquiposHistoricoAgregar->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(TenantPermission::EquiposCrear->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Equipo $equipo): bool
    {
        return $user->tenant_id === $equipo->tenant_id
            && $user->can(TenantPermission::EquiposEditar->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Equipo $equipo): bool
    {
        return $user->tenant_id === $equipo->tenant_id
            && $user->can(TenantPermission::EquiposEliminar->value);
    }
}
