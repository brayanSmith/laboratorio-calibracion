<?php

namespace App\Modules\Importaciones\Policies;

use App\Models\User;
use App\Modules\Importaciones\Models\Importacion;

class ImportacionPolicy
{
    /**
     * Determine whether the user can view the model: solo quien inició la importación.
     */
    public function view(User $user, Importacion $importacion): bool
    {
        return $user->id === $importacion->user_id
            && $user->tenant_id === $importacion->tenant_id;
    }
}
