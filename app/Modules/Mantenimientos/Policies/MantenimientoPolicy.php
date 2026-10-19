<?php

namespace App\Modules\Mantenimientos\Policies;

use App\Enums\TenantPermission;
use App\Models\User;
use App\Modules\Mantenimientos\Models\Mantenimiento;

class MantenimientoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::MantenimientosVer->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->tenant_id === $mantenimiento->tenant_id
            && $mantenimiento->esVisiblePara($user)
            && $user->can(TenantPermission::MantenimientosEditar->value);
    }

    /**
     * Determine whether the user can start the model.
     */
    public function iniciar(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->tenant_id === $mantenimiento->tenant_id
            && $mantenimiento->esVisiblePara($user)
            && $user->can(TenantPermission::MantenimientosIniciar->value);
    }

    /**
     * Determine whether the user can finish the model.
     */
    public function finalizar(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->tenant_id === $mantenimiento->tenant_id
            && $mantenimiento->esVisiblePara($user)
            && $user->can(TenantPermission::MantenimientosFinalizar->value);
    }

    /**
     * Determine whether the user can view the PDF report of the model.
     */
    public function verPdf(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->tenant_id === $mantenimiento->tenant_id
            && $mantenimiento->esVisiblePara($user)
            && $user->can(TenantPermission::MantenimientosPdf->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Mantenimiento $mantenimiento): bool
    {
        return $user->tenant_id === $mantenimiento->tenant_id
            && $mantenimiento->esVisiblePara($user)
            && $user->can(TenantPermission::MantenimientosEliminar->value);
    }
}
