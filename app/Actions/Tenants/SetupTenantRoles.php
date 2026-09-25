<?php

namespace App\Actions\Tenants;

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SetupTenantRoles
{
    /**
     * Make sure the tenant has its default roles and, optionally, give its first user the Administrador role.
     *
     * Roles that already exist keep the permissions the tenant chose for them, except the
     * Administrador role, which always holds every permission so a tenant can never lock itself out.
     */
    public function handle(Tenant $tenant, ?User $admin = null): Role
    {
        foreach (TenantPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        return $this->withinTenant($tenant, function () use ($tenant, $admin): Role {
            foreach (TenantRole::cases() as $case) {
                $role = Role::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('name', $case->value)
                    ->first();

                if (! $role) {
                    $role = Role::create(['name' => $case->value, 'tenant_id' => $tenant->id]);
                    $role->syncPermissions($this->permissionNames($case));
                } elseif ($case->isSystem()) {
                    $role->syncPermissions($this->permissionNames($case));
                }
            }

            $administrador = Role::findByName(TenantRole::Administrador->value);

            $admin?->assignRole($administrador);

            return $administrador;
        });
    }

    /**
     * Assign one of the tenant's roles to a user of that tenant.
     */
    public function assignRole(Tenant $tenant, User $user, TenantRole $role): void
    {
        $this->withinTenant($tenant, fn () => $user->assignRole($role->value));
    }

    /**
     * Give the Administrador role to every tenant user that currently has no role at all.
     */
    public function assignAdministradorToUsersWithoutRole(Tenant $tenant): int
    {
        return $this->withinTenant($tenant, function () use ($tenant): int {
            $assigned = 0;

            $tenant->users()->where('is_platform_admin', false)->each(function (User $user) use (&$assigned): void {
                if ($user->roles()->doesntExist()) {
                    $user->assignRole(TenantRole::Administrador->value);
                    $assigned++;
                }
            });

            return $assigned;
        });
    }

    /**
     * Run the callback with the permission team context switched to the given tenant.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withinTenant(Tenant $tenant, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTenantId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($tenant->id);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previousTenantId);
        }
    }

    /**
     * @return array<int, string>
     */
    private function permissionNames(TenantRole $role): array
    {
        return array_map(fn (TenantPermission $permission) => $permission->value, $role->permissions());
    }
}
