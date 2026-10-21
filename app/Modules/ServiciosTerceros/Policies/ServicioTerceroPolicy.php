<?php

namespace App\Modules\ServiciosTerceros\Policies;

use App\Enums\TenantPermission;
use App\Models\User;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;

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

        return $this->gestiona($user, $servicioTercero, $permiso);
    }

    /**
     * Determine whether the user can start the model.
     */
    public function iniciar(User $user, ServicioTercero $servicioTercero): bool
    {
        $permiso = $servicioTercero->tipo_servicio === 'MANTENIMIENTO'
            ? TenantPermission::MantenimientosIniciar
            : TenantPermission::CalibracionesIniciar;

        return $this->gestiona($user, $servicioTercero, $permiso);
    }

    /**
     * Determine whether the user can finish the model.
     */
    public function finalizar(User $user, ServicioTercero $servicioTercero): bool
    {
        $permiso = $servicioTercero->tipo_servicio === 'MANTENIMIENTO'
            ? TenantPermission::MantenimientosFinalizar
            : TenantPermission::CalibracionesFinalizar;

        return $this->gestiona($user, $servicioTercero, $permiso);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ServicioTercero $servicioTercero): bool
    {
        $permiso = $servicioTercero->tipo_servicio === 'MANTENIMIENTO'
            ? TenantPermission::MantenimientosEliminar
            : TenantPermission::CalibracionesEliminar;

        return $this->gestiona($user, $servicioTercero, $permiso);
    }

    /**
     * Además del permiso de la acción, exige el de terceros del módulo del servicio.
     */
    private function gestiona(User $user, ServicioTercero $servicioTercero, TenantPermission $permiso): bool
    {
        return $user->tenant_id === $servicioTercero->tenant_id
            && $user->can($permiso->value)
            && $user->can(TenantPermission::tercerosDe($servicioTercero->tipo_servicio)->value);
    }
}
