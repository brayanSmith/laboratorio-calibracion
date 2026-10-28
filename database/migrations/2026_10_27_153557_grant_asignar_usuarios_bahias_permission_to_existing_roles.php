<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los roles que ya pueden editar bahías también pueden asignarles usuarios.
     */
    public function up(): void
    {
        Permission::findOrCreate('bahias.asignar-usuarios');

        Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'bahias.editar'))
            ->each(fn (Role $role) => $role->givePermissionTo('bahias.asignar-usuarios'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'bahias.asignar-usuarios')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
