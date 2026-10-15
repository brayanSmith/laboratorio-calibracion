<?php

use App\Enums\TenantPermission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Para no quitarle botones a nadie: quien podía editar equipos conserva los atajos de
     * agendar, y quien podía ver mantenimientos, calibraciones o despachos conserva la agenda.
     *
     * @return array<string, array<int, TenantPermission>>
     */
    private function grants(): array
    {
        return [
            'equipos.editar' => [
                TenantPermission::AgendarMantenimiento,
                TenantPermission::AgendarCalibracion,
                TenantPermission::AgendarDespachos,
            ],
            'mantenimientos.ver' => [TenantPermission::AgendaVer],
            'calibraciones.ver' => [TenantPermission::AgendaVer],
            'despachos.ver' => [TenantPermission::AgendaVer],
        ];
    }

    public function up(): void
    {
        foreach ([TenantPermission::AgendaVer, TenantPermission::AgendarMantenimiento, TenantPermission::AgendarCalibracion, TenantPermission::AgendarDespachos] as $permission) {
            Permission::findOrCreate($permission->value);
        }

        foreach ($this->grants() as $existing => $new) {
            Role::query()
                ->whereHas('permissions', fn ($query) => $query->where('name', $existing))
                ->each(fn (Role $role) => $role->givePermissionTo(array_map(fn (TenantPermission $permission) => $permission->value, $new)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ([TenantPermission::AgendaVer, TenantPermission::AgendarMantenimiento, TenantPermission::AgendarCalibracion, TenantPermission::AgendarDespachos] as $permission) {
            Permission::query()->where('name', $permission->value)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
