<?php

use App\Enums\TenantPermission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Antes se podía importar un tipo con el permiso de crearlo (el checklist, con el de
     * gestionarlo): los roles que ya lo tenían reciben el permiso de importación nuevo, para
     * que nadie pierda acceso.
     *
     * @var array<string, TenantPermission>
     */
    private function grants(): array
    {
        return [
            'equipos.crear' => TenantPermission::ImportarEquipos,
            'items.crear' => TenantPermission::ImportarItems,
            'clientes.crear' => TenantPermission::ImportarClientes,
            'fabricantes.crear' => TenantPermission::ImportarFabricantes,
            'procedimientos-calibracion.crear' => TenantPermission::ImportarProcedimientosCalibracion,
            'novedades.crear' => TenantPermission::ImportarNovedades,
            'laboratorios.crear' => TenantPermission::ImportarLaboratorios,
            'areas.crear' => TenantPermission::ImportarAreas,
            'bahias.crear' => TenantPermission::ImportarBahias,
            'empresas-terceras.crear' => TenantPermission::ImportarEmpresasTerceras,
            'tipos-equipo.crear' => TenantPermission::ImportarTiposEquipo,
            'tipos-equipo.checklist' => TenantPermission::ImportarChecklistMantenimiento,
        ];
    }

    public function up(): void
    {
        foreach ($this->grants() as $existing => $new) {
            Permission::findOrCreate($new->value);

            Role::query()
                ->whereHas('permissions', fn ($query) => $query->where('name', $existing))
                ->each(fn (Role $role) => $role->givePermissionTo($new->value));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->grants() as $new) {
            Permission::query()->where('name', $new->value)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
