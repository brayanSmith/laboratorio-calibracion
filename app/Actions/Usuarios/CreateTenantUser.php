<?php

namespace App\Actions\Usuarios;

use App\Models\Tenant;
use App\Models\User;

class CreateTenantUser
{
    /**
     * Create a user that belongs to the tenant and must replace the given temporary password on first login.
     */
    public function handle(Tenant $tenant, string $name, string $email, string $temporaryPassword): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => $temporaryPassword,
        ]);
        $user->tenant_id = $tenant->id;
        $user->must_change_password = true;
        $user->save();

        return $user;
    }
}
