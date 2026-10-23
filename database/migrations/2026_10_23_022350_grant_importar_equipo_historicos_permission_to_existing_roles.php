<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los roles que ya pueden importar equipos también pueden importar su histórico.
     */
    public function up(): void
    {
        Permission::findOrCreate('importaciones.equipo-historicos');

        Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'importaciones.equipos'))
            ->each(fn (Role $role) => $role->givePermissionTo('importaciones.equipo-historicos'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'importaciones.equipo-historicos')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
