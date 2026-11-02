<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Indicadores nuevos del dashboard. */
    private const PERMISOS = [
        'dashboard.tiempos-atencion',
        'dashboard.desempeno-tecnicos',
        'dashboard.cumplimiento',
    ];

    /**
     * Los roles que ya ven el dashboard completo (el total de equipos) también ven sus
     * indicadores.
     */
    public function up(): void
    {
        foreach (self::PERMISOS as $permiso) {
            Permission::findOrCreate($permiso);
        }

        Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'dashboard.todos-equipos'))
            ->each(fn (Role $role) => $role->givePermissionTo(self::PERMISOS));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', self::PERMISOS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
