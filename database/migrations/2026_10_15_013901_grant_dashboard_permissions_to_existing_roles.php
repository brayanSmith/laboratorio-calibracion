<?php

use App\Enums\TenantPermission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los roles que ya existían siguen viendo todo el dashboard: reciben todos sus
     * bloques y el tenant puede quitarlos después desde la pantalla de roles.
     */
    public function up(): void
    {
        $permissions = array_map(fn (TenantPermission $permission) => $permission->value, TenantPermission::dashboardCases());

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        Role::query()->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (TenantPermission::dashboardCases() as $permission) {
            Permission::query()->where('name', $permission->value)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
