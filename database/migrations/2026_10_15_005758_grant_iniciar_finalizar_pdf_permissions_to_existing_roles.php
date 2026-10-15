<?php

use App\Enums\TenantPermission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permisos nuevos por módulo: los roles que ya podían editar conservan iniciar y
     * finalizar, y los que ya podían ver conservan el PDF, para que nadie pierda acceso.
     *
     * @var array<string, array<int, TenantPermission>>
     */
    private function grants(): array
    {
        return [
            'mantenimientos.editar' => [TenantPermission::MantenimientosIniciar, TenantPermission::MantenimientosFinalizar],
            'mantenimientos.ver' => [TenantPermission::MantenimientosPdf],
            'calibraciones.editar' => [TenantPermission::CalibracionesIniciar, TenantPermission::CalibracionesFinalizar],
            'calibraciones.ver' => [TenantPermission::CalibracionesPdf],
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
