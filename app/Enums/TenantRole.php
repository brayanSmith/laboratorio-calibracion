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
                TenantPermission::ClientesVer,
                TenantPermission::ClientesCrear,
                TenantPermission::ClientesEditar,
                TenantPermission::EquiposVer,
                TenantPermission::EquiposCrear,
                TenantPermission::EquiposEditar,
                TenantPermission::IngresosVer,
                TenantPermission::IngresosCrear,
                TenantPermission::IngresosEditar,
                TenantPermission::TiposEquipoVer,
                TenantPermission::TiposEquipoCrear,
                TenantPermission::TiposEquipoEditar,
                TenantPermission::AreasVer,
                TenantPermission::AreasCrear,
                TenantPermission::AreasEditar,
                TenantPermission::BahiasVer,
                TenantPermission::BahiasCrear,
                TenantPermission::BahiasEditar,
                TenantPermission::FabricantesVer,
                TenantPermission::FabricantesCrear,
                TenantPermission::FabricantesEditar,
                TenantPermission::ItemsVer,
                TenantPermission::ItemsCrear,
                TenantPermission::ItemsEditar,
                TenantPermission::EmpresasTercerasVer,
                TenantPermission::EmpresasTercerasCrear,
                TenantPermission::EmpresasTercerasEditar,
                TenantPermission::LaboratoriosVer,
                TenantPermission::LaboratoriosCrear,
                TenantPermission::LaboratoriosEditar,
                TenantPermission::ProcedimientosCalibracionVer,
                TenantPermission::ProcedimientosCalibracionCrear,
                TenantPermission::ProcedimientosCalibracionEditar,
                TenantPermission::TiposMagnitudVer,
                TenantPermission::TiposMagnitudCrear,
                TenantPermission::TiposMagnitudEditar,
                TenantPermission::UnidadesMedidaVer,
                TenantPermission::UnidadesMedidaCrear,
                TenantPermission::UnidadesMedidaEditar,
                TenantPermission::EmpresaVer,
            ],
            self::Tecnico => [
                TenantPermission::ClientesVer,
                TenantPermission::EquiposVer,
                TenantPermission::EquiposEditar,
                TenantPermission::IngresosVer,
                TenantPermission::IngresosCrear,
                TenantPermission::TiposEquipoVer,
                TenantPermission::AreasVer,
                TenantPermission::BahiasVer,
                TenantPermission::FabricantesVer,
                TenantPermission::ItemsVer,
                TenantPermission::EmpresasTercerasVer,
                TenantPermission::LaboratoriosVer,
                TenantPermission::ProcedimientosCalibracionVer,
                TenantPermission::TiposMagnitudVer,
                TenantPermission::UnidadesMedidaVer,
                TenantPermission::EmpresaVer,
            ],
            self::Recepcion => [
                TenantPermission::ClientesVer,
                TenantPermission::ClientesCrear,
                TenantPermission::EquiposVer,
                TenantPermission::EquiposCrear,
                TenantPermission::IngresosVer,
                TenantPermission::IngresosCrear,
                TenantPermission::TiposEquipoVer,
                TenantPermission::AreasVer,
                TenantPermission::BahiasVer,
                TenantPermission::FabricantesVer,
                TenantPermission::ItemsVer,
                TenantPermission::EmpresasTercerasVer,
                TenantPermission::LaboratoriosVer,
                TenantPermission::ProcedimientosCalibracionVer,
                TenantPermission::TiposMagnitudVer,
                TenantPermission::UnidadesMedidaVer,
                TenantPermission::EmpresaVer,
            ],
        };
    }
}
