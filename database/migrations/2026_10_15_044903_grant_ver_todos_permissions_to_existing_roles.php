<?php

use App\Enums\TenantPermission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Hasta ahora cualquier rol veía los servicios de todos los técnicos: para no cambiar
     * lo que ven hoy, los roles que ya podían ver cada módulo reciben su permiso
     * "ver todos". El tenant puede quitarlo después desde la pantalla de roles.
     *
     * @return array<string, TenantPermission>
     */
    private function grants(): array
    {
        return [
            'mantenimientos.ver' => TenantPermission::MantenimientosVerTodos,
            'calibraciones.ver' => TenantPermission::CalibracionesVerTodos,
            'despachos.ver' => TenantPermission::DespachosVerTodos,
            'agenda.ver' => TenantPermission::AgendaVerTodos,
        ];
    }

    public function up(): void
    {
        foreach (TenantPermission::verTodosCases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        foreach ($this->grants() as $existing => $new) {
            Role::query()
                ->whereHas('permissions', fn ($query) => $query->where('name', $existing))
                ->each(fn (Role $role) => $role->givePermissionTo($new->value));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (TenantPermission::verTodosCases() as $permission) {
            Permission::query()->where('name', $permission->value)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
