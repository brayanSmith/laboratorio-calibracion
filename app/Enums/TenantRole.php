<?php

namespace App\Enums;

enum TenantRole: string
{
    case Administrador = 'Administrador';
    case Metrologo = 'Metrólogo';
    case Tecnico = 'Técnico';
    case Recepcion = 'Recepción';

    /**
     * Determine if the role is required by the platform and cannot be removed or restricted by a tenant.
     */
    public function isSystem(): bool
    {
        return $this === self::Administrador;
    }

    /**
     * Get the permissions a tenant receives for this role when it is first created.
     *
     * @return array<TenantPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Administrador => TenantPermission::cases(),
            self::Metrologo => [
                TenantPermission::EquiposVer,
                TenantPermission::EquiposCrear,
                TenantPermission::EquiposEditar,
                TenantPermission::TiposEquipoVer,
                TenantPermission::TiposEquipoCrear,
                TenantPermission::TiposEquipoEditar,
                TenantPermission::EmpresaVer,
            ],
            self::Tecnico => [
                TenantPermission::EquiposVer,
                TenantPermission::EquiposEditar,
                TenantPermission::TiposEquipoVer,
                TenantPermission::EmpresaVer,
            ],
            self::Recepcion => [
                TenantPermission::EquiposVer,
                TenantPermission::EquiposCrear,
                TenantPermission::TiposEquipoVer,
                TenantPermission::EmpresaVer,
            ],
        };
    }
}
