<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\ServicioTercero;
use App\Models\User;

class ServicioTerceroPolicy
{
    /**
     * Determine whether the user can update the model. Se usan los permisos del módulo
     * al que pertenece el servicio (mantenimientos o calibraciones).
     */
    public function update(User $user, ServicioTercero $servicioTercero): bool
    {
        $permiso = $servicioTercero->tipo_servicio === 'MANTENIMIENTO'
            ? TenantPermission::MantenimientosEditar
            : TenantPermission::CalibracionesEditar;

        return $user->tenant_id === $servicioTercero->tenant_id
            && $user->can($permiso->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ServicioTercero $servicioTercero): bool
    {
        $permiso = $servicioTercero->tipo_servicio === 'MANTENIMIENTO'
            ? TenantPermission::MantenimientosEliminar
            : TenantPermission::CalibracionesEliminar;

        return $user->tenant_id === $servicioTercero->tenant_id
            && $user->can($permiso->value);
    }
}
