<?php

namespace App\Enums;

enum TenantPermission: string
{
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

    case FabricantesVer = 'fabricantes.ver';
    case FabricantesCrear = 'fabricantes.crear';
    case FabricantesEditar = 'fabricantes.editar';
    case FabricantesEliminar = 'fabricantes.eliminar';

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
            self::FabricantesVer,
            self::FabricantesCrear,
            self::FabricantesEditar,
            self::FabricantesEliminar => 'Fabricantes',
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
            self::FabricantesVer => 'Ver fabricantes',
            self::FabricantesCrear => 'Crear fabricantes',
            self::FabricantesEditar => 'Editar fabricantes',
            self::FabricantesEliminar => 'Eliminar fabricantes',
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
