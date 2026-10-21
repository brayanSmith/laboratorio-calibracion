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
        $base = match ($this) {
            self::Administrador => TenantPermission::cases(),
            self::Metrologo => [
                TenantPermission::ClientesVer,
                TenantPermission::ClientesCrear,
                TenantPermission::ClientesEditar,
                TenantPermission::EquiposVer,
                TenantPermission::EquiposCrear,
                TenantPermission::EquiposEditar,
                TenantPermission::EquiposHistorial,
                TenantPermission::EquiposProgramaciones,
                TenantPermission::IngresosVer,
                TenantPermission::IngresosCrear,
                TenantPermission::IngresosEditar,
                TenantPermission::MantenimientosVer,
                TenantPermission::MantenimientosEditar,
                TenantPermission::MantenimientosIniciar,
                TenantPermission::MantenimientosFinalizar,
                TenantPermission::MantenimientosPdf,
                TenantPermission::MantenimientosTerceros,
                TenantPermission::CalibracionesVer,
                TenantPermission::CalibracionesEditar,
                TenantPermission::CalibracionesIniciar,
                TenantPermission::CalibracionesFinalizar,
                TenantPermission::CalibracionesPdf,
                TenantPermission::CalibracionesTerceros,
                TenantPermission::DespachosVer,
                TenantPermission::DespachosEditar,
                TenantPermission::TiposEquipoVer,
                TenantPermission::TiposEquipoCrear,
                TenantPermission::TiposEquipoEditar,
                TenantPermission::TiposEquipoChecklist,
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
                TenantPermission::ItemsRegistrarEntrada,
                TenantPermission::ItemsVerEntradas,
                TenantPermission::ItemsRecibirEntradas,
                TenantPermission::EmpresasTercerasVer,
                TenantPermission::EmpresasTercerasCrear,
                TenantPermission::EmpresasTercerasEditar,
                TenantPermission::LaboratoriosVer,
                TenantPermission::LaboratoriosCrear,
                TenantPermission::LaboratoriosEditar,
                TenantPermission::ProcedimientosCalibracionVer,
                TenantPermission::ProcedimientosCalibracionCrear,
                TenantPermission::ProcedimientosCalibracionEditar,
                TenantPermission::AlcancesMedicionVer,
                TenantPermission::AlcancesMedicionCrear,
                TenantPermission::AlcancesMedicionEditar,
                TenantPermission::NovedadesVer,
                TenantPermission::NovedadesCrear,
                TenantPermission::NovedadesEditar,
                TenantPermission::TiposMagnitudVer,
                TenantPermission::TiposMagnitudCrear,
                TenantPermission::TiposMagnitudEditar,
                TenantPermission::UnidadesMedidaVer,
                TenantPermission::UnidadesMedidaCrear,
                TenantPermission::UnidadesMedidaEditar,
                TenantPermission::EmpresaVer,
                ...array_map(fn (TipoImportacion $tipo) => $tipo->permiso(), TipoImportacion::cases()),
            ],
            self::Tecnico => [
                TenantPermission::ClientesVer,
                TenantPermission::EquiposVer,
                TenantPermission::EquiposEditar,
                TenantPermission::EquiposHistorial,
                TenantPermission::EquiposProgramaciones,
                TenantPermission::IngresosVer,
                TenantPermission::IngresosCrear,
                TenantPermission::MantenimientosVer,
                TenantPermission::MantenimientosEditar,
                TenantPermission::MantenimientosIniciar,
                TenantPermission::MantenimientosFinalizar,
                TenantPermission::MantenimientosPdf,
                TenantPermission::MantenimientosTerceros,
                TenantPermission::CalibracionesVer,
                TenantPermission::CalibracionesEditar,
                TenantPermission::CalibracionesIniciar,
                TenantPermission::CalibracionesFinalizar,
                TenantPermission::CalibracionesPdf,
                TenantPermission::CalibracionesTerceros,
                TenantPermission::DespachosVer,
                TenantPermission::DespachosEditar,
                TenantPermission::TiposEquipoVer,
                TenantPermission::AreasVer,
                TenantPermission::BahiasVer,
                TenantPermission::FabricantesVer,
                TenantPermission::ItemsVer,
                TenantPermission::ItemsVerEntradas,
                TenantPermission::EmpresasTercerasVer,
                TenantPermission::LaboratoriosVer,
                TenantPermission::ProcedimientosCalibracionVer,
                TenantPermission::AlcancesMedicionVer,
                TenantPermission::NovedadesVer,
                TenantPermission::TiposMagnitudVer,
                TenantPermission::UnidadesMedidaVer,
                TenantPermission::EmpresaVer,
            ],
            self::Recepcion => [
                TenantPermission::ClientesVer,
                TenantPermission::ClientesCrear,
                TenantPermission::EquiposVer,
                TenantPermission::EquiposCrear,
                TenantPermission::EquiposHistorial,
                TenantPermission::EquiposProgramaciones,
                TenantPermission::IngresosVer,
                TenantPermission::IngresosCrear,
                TenantPermission::MantenimientosVer,
                TenantPermission::MantenimientosPdf,
                TenantPermission::MantenimientosTerceros,
                TenantPermission::CalibracionesVer,
                TenantPermission::CalibracionesPdf,
                TenantPermission::CalibracionesTerceros,
                TenantPermission::DespachosVer,
                TenantPermission::TiposEquipoVer,
                TenantPermission::AreasVer,
                TenantPermission::BahiasVer,
                TenantPermission::FabricantesVer,
                TenantPermission::ItemsVer,
                TenantPermission::ItemsVerEntradas,
                TenantPermission::EmpresasTercerasVer,
                TenantPermission::LaboratoriosVer,
                TenantPermission::ProcedimientosCalibracionVer,
                TenantPermission::AlcancesMedicionVer,
                TenantPermission::NovedadesVer,
                TenantPermission::TiposMagnitudVer,
                TenantPermission::UnidadesMedidaVer,
                TenantPermission::EmpresaVer,
                TenantPermission::ImportarEquipos,
                TenantPermission::ImportarClientes,
            ],
        };

        $agendar = in_array(TenantPermission::EquiposEditar, $base, true) ? [
            TenantPermission::AgendarMantenimiento,
            TenantPermission::AgendarCalibracion,
            TenantPermission::AgendarDespachos,
        ] : [];

        return array_values(array_unique([
            ...$base,
            ...TenantPermission::dashboardCases(),
            TenantPermission::AgendaVer,
            ...$agendar,
            ...($this === self::Tecnico ? [] : TenantPermission::verTodosCases()),
        ], SORT_REGULAR));
    }
}
