<?php

use App\Enums\TenantPermission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permisos nuevos que separan acciones antes cubiertas por un permiso general: los
     * roles que ya tenían ese permiso reciben los nuevos, para que nadie pierda acceso.
     *
     * @var array<string, array<int, TenantPermission>>
     */
    private function grants(): array
    {
        return [
            'equipos.ver' => [TenantPermission::EquiposHistorial, TenantPermission::EquiposProgramaciones],
            'mantenimientos.ver' => [TenantPermission::MantenimientosTerceros],
            'calibraciones.ver' => [TenantPermission::CalibracionesTerceros],
            'tipos-equipo.editar' => [TenantPermission::TiposEquipoChecklist],
            'items.ver' => [TenantPermission::ItemsVerEntradas],
            'items.editar' => [TenantPermission::ItemsRegistrarEntrada, TenantPermission::ItemsRecibirEntradas],
        ];
    }

    public function up(): void
    {
        foreach ($this->grants() as $existing => $new) {
            foreach ($new as $permission) {
                Permission::findOrCreate($permission->value);
            }

            Role::query()
                ->whereHas('permissions', fn ($query) => $query->where('name', $existing))
                ->each(fn (Role $role) => $role->givePermissionTo(array_map(fn (TenantPermission $p) => $p->value, $new)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->grants() as $new) {
            foreach ($new as $permission) {
                Permission::query()->where('name', $permission->value)->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
