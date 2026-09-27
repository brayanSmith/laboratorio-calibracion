<?php

namespace App\Enums;

enum TenantPermission: string
{
    case ClientesVer = 'clientes.ver';
    case ClientesCrear = 'clientes.crear';
    case ClientesEditar = 'clientes.editar';
    case ClientesEliminar = 'clientes.eliminar';

    case EquiposVer = 'equipos.ver';
    case EquiposCrear = 'equipos.crear';
    case EquiposEditar = 'equipos.editar';
    case EquiposEliminar = 'equipos.eliminar';

    case TiposEquipoVer = 'tipos-equipo.ver';
    case TiposEquipoCrear = 'tipos-equipo.crear';
    case TiposEquipoEditar = 'tipos-equipo.editar';
    case TiposEquipoEliminar = 'tipos-equipo.eliminar';

    case AreasVer = 'areas.ver';
    case AreasCrear = 'areas.crear';
    case AreasEditar = 'areas.editar';
    case AreasEliminar = 'areas.eliminar';

    case BahiasVer = 'bahias.ver';
    case BahiasCrear = 'bahias.crear';
    case BahiasEditar = 'bahias.editar';
    case BahiasEliminar = 'bahias.eliminar';

    case FabricantesVer = 'fabricantes.ver';
    case FabricantesCrear = 'fabricantes.crear';
    case FabricantesEditar = 'fabricantes.editar';
    case FabricantesEliminar = 'fabricantes.eliminar';

    case ItemsVer = 'items.ver';
    case ItemsCrear = 'items.crear';
    case ItemsEditar = 'items.editar';
    case ItemsEliminar = 'items.eliminar';

    case EmpresasTercerasVer = 'empresas-terceras.ver';
    case EmpresasTercerasCrear = 'empresas-terceras.crear';
    case EmpresasTercerasEditar = 'empresas-terceras.editar';
    case EmpresasTercerasEliminar = 'empresas-terceras.eliminar';

    case LaboratoriosVer = 'laboratorios.ver';
    case LaboratoriosCrear = 'laboratorios.crear';
    case LaboratoriosEditar = 'laboratorios.editar';
    case LaboratoriosEliminar = 'laboratorios.eliminar';

    case ProcedimientosCalibracionVer = 'procedimientos-calibracion.ver';
    case ProcedimientosCalibracionCrear = 'procedimientos-calibracion.crear';
    case ProcedimientosCalibracionEditar = 'procedimientos-calibracion.editar';
    case ProcedimientosCalibracionEliminar = 'procedimientos-calibracion.eliminar';

    case TiposMagnitudVer = 'tipos-magnitud.ver';
    case TiposMagnitudCrear = 'tipos-magnitud.crear';
    case TiposMagnitudEditar = 'tipos-magnitud.editar';
    case TiposMagnitudEliminar = 'tipos-magnitud.eliminar';

    case UnidadesMedidaVer = 'unidades-medida.ver';
    case UnidadesMedidaCrear = 'unidades-medida.crear';
    case UnidadesMedidaEditar = 'unidades-medida.editar';
    case UnidadesMedidaEliminar = 'unidades-medida.eliminar';

    case EmpresaVer = 'empresa.ver';
    case EmpresaCrear = 'empresa.crear';
    case EmpresaEditar = 'empresa.editar';
    case EmpresaEliminar = 'empresa.eliminar';

    case RolesGestionar = 'roles.gestionar';
    case UsuariosGestionar = 'usuarios.gestionar';

    /**
     * Get the module the permission belongs to.
     */
    public function group(): string
    {
        return match ($this) {
            self::ClientesVer,
            self::ClientesCrear,
            self::ClientesEditar,
            self::ClientesEliminar => 'Clientes',
            self::EquiposVer,
            self::EquiposCrear,
            self::EquiposEditar,
            self::EquiposEliminar => 'Equipos',
            self::TiposEquipoVer,
            self::TiposEquipoCrear,
            self::TiposEquipoEditar,
            self::TiposEquipoEliminar => 'Tipos de equipo',
            self::AreasVer,
            self::AreasCrear,
            self::AreasEditar,
            self::AreasEliminar => 'Áreas',
            self::BahiasVer,
            self::BahiasCrear,
            self::BahiasEditar,
            self::BahiasEliminar => 'Bahías',
            self::FabricantesVer,
            self::FabricantesCrear,
            self::FabricantesEditar,
            self::FabricantesEliminar => 'Fabricantes',
            self::ItemsVer,
            self::ItemsCrear,
            self::ItemsEditar,
            self::ItemsEliminar => 'Ítems',
            self::EmpresasTercerasVer,
            self::EmpresasTercerasCrear,
            self::EmpresasTercerasEditar,
            self::EmpresasTercerasEliminar => 'Empresas terceras',
            self::LaboratoriosVer,
            self::LaboratoriosCrear,
            self::LaboratoriosEditar,
            self::LaboratoriosEliminar => 'Laboratorios',
            self::ProcedimientosCalibracionVer,
            self::ProcedimientosCalibracionCrear,
            self::ProcedimientosCalibracionEditar,
            self::ProcedimientosCalibracionEliminar => 'Procedimientos de calibración',
            self::TiposMagnitudVer,
            self::TiposMagnitudCrear,
            self::TiposMagnitudEditar,
            self::TiposMagnitudEliminar => 'Tipos de magnitud',
            self::UnidadesMedidaVer,
            self::UnidadesMedidaCrear,
            self::UnidadesMedidaEditar,
            self::UnidadesMedidaEliminar => 'Unidades de medida',
            self::EmpresaVer,
            self::EmpresaCrear,
            self::EmpresaEditar,
            self::EmpresaEliminar => 'Empresa',
            self::RolesGestionar => 'Roles',
            self::UsuariosGestionar => 'Usuarios',
        };
    }

    /**
     * Get the display label for the permission.
     */
    public function label(): string
    {
        return match ($this) {
            self::ClientesVer => 'Ver clientes',
            self::ClientesCrear => 'Crear clientes',
            self::ClientesEditar => 'Editar clientes',
            self::ClientesEliminar => 'Eliminar clientes',
            self::EquiposVer => 'Ver equipos',
            self::EquiposCrear => 'Crear equipos',
            self::EquiposEditar => 'Editar equipos',
            self::EquiposEliminar => 'Eliminar equipos',
            self::TiposEquipoVer => 'Ver tipos de equipo',
            self::TiposEquipoCrear => 'Crear tipos de equipo',
            self::TiposEquipoEditar => 'Editar tipos de equipo',
            self::TiposEquipoEliminar => 'Eliminar tipos de equipo',
            self::AreasVer => 'Ver áreas',
            self::AreasCrear => 'Crear áreas',
            self::AreasEditar => 'Editar áreas',
            self::AreasEliminar => 'Eliminar áreas',
            self::BahiasVer => 'Ver bahías',
            self::BahiasCrear => 'Crear bahías',
            self::BahiasEditar => 'Editar bahías',
            self::BahiasEliminar => 'Eliminar bahías',
            self::FabricantesVer => 'Ver fabricantes',
            self::FabricantesCrear => 'Crear fabricantes',
            self::FabricantesEditar => 'Editar fabricantes',
            self::FabricantesEliminar => 'Eliminar fabricantes',
            self::ItemsVer => 'Ver ítems',
            self::ItemsCrear => 'Crear ítems',
            self::ItemsEditar => 'Editar ítems',
            self::ItemsEliminar => 'Eliminar ítems',
            self::EmpresasTercerasVer => 'Ver empresas terceras',
            self::EmpresasTercerasCrear => 'Crear empresas terceras',
            self::EmpresasTercerasEditar => 'Editar empresas terceras',
            self::EmpresasTercerasEliminar => 'Eliminar empresas terceras',
            self::LaboratoriosVer => 'Ver laboratorios',
            self::LaboratoriosCrear => 'Crear laboratorios',
            self::LaboratoriosEditar => 'Editar laboratorios',
            self::LaboratoriosEliminar => 'Eliminar laboratorios',
            self::ProcedimientosCalibracionVer => 'Ver procedimientos de calibración',
            self::ProcedimientosCalibracionCrear => 'Crear procedimientos de calibración',
            self::ProcedimientosCalibracionEditar => 'Editar procedimientos de calibración',
            self::ProcedimientosCalibracionEliminar => 'Eliminar procedimientos de calibración',
            self::TiposMagnitudVer => 'Ver tipos de magnitud',
            self::TiposMagnitudCrear => 'Crear tipos de magnitud',
            self::TiposMagnitudEditar => 'Editar tipos de magnitud',
            self::TiposMagnitudEliminar => 'Eliminar tipos de magnitud',
            self::UnidadesMedidaVer => 'Ver unidades de medida',
            self::UnidadesMedidaCrear => 'Crear unidades de medida',
            self::UnidadesMedidaEditar => 'Editar unidades de medida',
            self::UnidadesMedidaEliminar => 'Eliminar unidades de medida',
            self::EmpresaVer => 'Ver empresa',
            self::EmpresaCrear => 'Registrar empresa',
            self::EmpresaEditar => 'Editar empresa',
            self::EmpresaEliminar => 'Eliminar empresa',
            self::RolesGestionar => 'Gestionar roles',
            self::UsuariosGestionar => 'Gestionar usuarios',
        };
    }

    /**
     * Get every permission grouped by module, ready to be rendered as a matrix.
     *
     * @return array<int, array{group: string, permissions: array<int, array{value: string, label: string}>}>
     */
    public static function catalog(): array
    {
        return collect(self::cases())
            ->groupBy(fn (self $permission) => $permission->group())
            ->map(fn ($permissions, string $group) => [
                'group' => $group,
                'permissions' => $permissions
                    ->map(fn (self $permission) => ['value' => $permission->value, 'label' => $permission->label()])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
