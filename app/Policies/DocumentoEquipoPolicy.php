<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\DocumentoEquipo;
use App\Models\User;

class DocumentoEquipoPolicy
{
    /**
     * Determine whether the user can delete the documento.
     *
     * The documento is managed as part of the equipo, so it requires the same permission.
     */
    public function delete(User $user, DocumentoEquipo $documentoEquipo): bool
    {
        return $user->tenant_id === $documentoEquipo->tenant_id
            && $user->can(TenantPermission::EquiposEditar->value);
    }
}
