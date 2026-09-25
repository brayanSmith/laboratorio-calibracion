<?php

namespace App\Concerns;

use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

trait ValidatesRoleAssignment
{
    /**
     * Guard the rules that keep a tenant manageable when a role is assigned to a user.
     */
    protected function validateRoleAssignment(Validator $validator, ?User $target = null): void
    {
        if ($validator->errors()->has('role_id')) {
            return;
        }

        $actor = $this->user();
        $role = Role::find($this->integer('role_id'));
        $administrador = TenantRole::Administrador->value;
        $assignsAdministrador = $role?->name === $administrador;

        if ($assignsAdministrador && ! $actor->hasRole($administrador)) {
            $validator->errors()->add('role_id', __('Solo un Administrador puede asignar el rol Administrador.'));

            return;
        }

        if (! $target) {
            return;
        }

        if ($target->is($actor) && ! $target->roles->contains('id', $role?->id)) {
            $validator->errors()->add('role_id', __('No puedes cambiar tu propio rol.'));
        }
    }
}
