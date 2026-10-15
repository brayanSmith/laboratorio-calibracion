<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Calibracion;
use App\Models\User;

class CalibracionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TenantPermission::CalibracionesVer->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Calibracion $calibracion): bool
    {
        return $user->tenant_id === $calibracion->tenant_id
            && $user->can(TenantPermission::CalibracionesEditar->value);
    }

    /**
     * Determine whether the user can start the model.
     */
    public function iniciar(User $user, Calibracion $calibracion): bool
    {
        return $user->tenant_id === $calibracion->tenant_id
            && $user->can(TenantPermission::CalibracionesIniciar->value);
    }

    /**
     * Determine whether the user can finish the model.
     */
    public function finalizar(User $user, Calibracion $calibracion): bool
    {
        return $user->tenant_id === $calibracion->tenant_id
            && $user->can(TenantPermission::CalibracionesFinalizar->value);
    }

    /**
     * Determine whether the user can view the PDF certificate of the model.
     */
    public function verPdf(User $user, Calibracion $calibracion): bool
    {
        return $user->tenant_id === $calibracion->tenant_id
            && $user->can(TenantPermission::CalibracionesPdf->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Calibracion $calibracion): bool
    {
        return $user->tenant_id === $calibracion->tenant_id
            && $user->can(TenantPermission::CalibracionesEliminar->value);
    }
}
