<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los roles que ya pueden importar bahías también pueden importar sus usuarios.
     */
    public function up(): void
    {
        Permission::findOrCreate('importaciones.bahia-usuarios');

        Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'importaciones.bahias'))
            ->each(fn (Role $role) => $role->givePermissionTo('importaciones.bahia-usuarios'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'importaciones.bahia-usuarios')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
