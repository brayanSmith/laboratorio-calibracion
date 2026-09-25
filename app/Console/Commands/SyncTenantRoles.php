<?php

namespace App\Console\Commands;

use App\Actions\Tenants\SetupTenantRoles;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tenants:sync-roles {--assign-admins : Asigna el rol Administrador a los usuarios de cada tenant que no tienen ningún rol}')]
#[Description('Crea los permisos y roles por defecto de cada tenant y actualiza el rol Administrador con los permisos nuevos')]
class SyncTenantRoles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SetupTenantRoles $setupTenantRoles): int
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $setupTenantRoles->handle($tenant);

            if ($this->option('assign-admins')) {
                $assigned = $setupTenantRoles->assignAdministradorToUsersWithoutRole($tenant);
                $this->line("{$tenant->nombre}: {$assigned} usuario(s) asignados como Administrador.");
            }
        }

        $this->info("Roles sincronizados en {$tenants->count()} tenant(s).");

        return self::SUCCESS;
    }
}
