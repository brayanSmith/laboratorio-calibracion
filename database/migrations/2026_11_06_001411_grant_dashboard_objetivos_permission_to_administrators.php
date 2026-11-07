<?php

use App\Enums\TenantRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Permiso para definir los objetivos anuales de los indicadores del dashboard. */
    private const PERMISO = 'dashboard.objetivos';

    /**
     * Los administradores de cada laboratorio pueden definir los objetivos; a los demás
     * roles se les asigna desde Roles.
     */
    public function up(): void
    {
        Permission::findOrCreate(self::PERMISO);

        Role::query()
            ->where('name', TenantRole::Administrador->value)
            ->each(fn (Role $role) => $role->givePermissionTo(self::PERMISO));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', self::PERMISO)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
