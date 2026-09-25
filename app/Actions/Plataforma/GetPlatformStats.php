<?php

namespace App\Actions\Plataforma;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GetPlatformStats
{
    /**
     * Aggregate figures about the platform. Never expose records that belong to a tenant.
     *
     * @return array{
     *     tenants: array{total: int, activos: int, inactivos: int},
     *     usuarios: array{total: int, pendientes: int},
     *     recientes: Collection<int, Tenant>,
     * }
     */
    public function handle(): array
    {
        $tenantsTotal = Tenant::count();
        $tenantsActivos = Tenant::activo()->count();
        $tenantUsers = User::query()->where('is_platform_admin', false)->whereNotNull('tenant_id');

        return [
            'tenants' => [
                'total' => $tenantsTotal,
                'activos' => $tenantsActivos,
                'inactivos' => $tenantsTotal - $tenantsActivos,
            ],
            'usuarios' => [
                'total' => (clone $tenantUsers)->count(),
                'pendientes' => (clone $tenantUsers)->where('must_change_password', true)->count(),
            ],
            'recientes' => Tenant::query()
                ->withCount('users')
                ->latest('id')
                ->limit(5)
                ->get(['id', 'nombre', 'slug', 'activo', 'created_at']),
        ];
    }
}
