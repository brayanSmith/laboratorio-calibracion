<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los roles que ya pueden editar equipos también pueden agregarles histórico a mano.
     */
    public function up(): void
    {
        Permission::findOrCreate('equipos.historico-agregar');

        Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'equipos.editar'))
            ->each(fn (Role $role) => $role->givePermissionTo('equipos.historico-agregar'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'equipos.historico-agregar')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
