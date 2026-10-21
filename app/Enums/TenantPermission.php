<?php

namespace App\Enums;

enum TenantPermission: string
{
    // "dashboard.ver" es obligatorio en todo rol; el resto son los bloques del dashboard que
    // el rol puede ver (ver DashboardController).
    case DashboardVer = 'dashboard.ver';
    case DashboardTodosEquipos = 'dashboard.todos-equipos';
    case DashboardEquiposAlDia = 'dashboard.equipos-al-dia';
    case DashboardEquiposProximos = 'dashboard.equipos-proximos';
    case DashboardEquiposVencidos = 'dashboard.equipos-vencidos';
    case DashboardEquiposUrgentes = 'dashboard.equipos-urgentes';
    case DashboardTablaEquipos = 'dashboard.tabla-equipos';

    // Botones del menú superior: la agenda y los atajos para agendar mantenimientos,
    // calibraciones y despachos.
    case AgendaVer = 'agenda.ver';
    case AgendaVerTodos = 'agenda.ver-todos';
    case AgendarMantenimiento = 'agendar.mantenimiento';
    case AgendarCalibracion = 'agendar.calibracion';
    case AgendarDespachos = 'agendar.despachos';

    case ClientesVer = 'clientes.ver';
    case ClientesCrear = 'clientes.crear';
    case ClientesEditar = 'clientes.editar';
    case ClientesEliminar = 'clientes.eliminar';

    case EquiposVer = 'equipos.ver';
    case EquiposCrear = 'equipos.crear';
    case EquiposEditar = 'equipos.editar';
    case EquiposEliminar = 'equipos.eliminar';
    case EquiposHistorial = 'equipos.historial';
    case EquiposProgramaciones = 'equipos.programaciones';

    case IngresosVer = 'ingresos.ver';
    case IngresosCrear = 'ingresos.crear';
    case IngresosEditar = 'ingresos.editar';
    case IngresosEliminar = 'ingresos.eliminar';

    // Sin "crear": un mantenimiento solo se origina desde "Agendar Mantenimiento" (ver
    // OrdenTrabajoController), no tiene un formulario de creación propio.
    case MantenimientosVer = 'mantenimientos.ver';
    case MantenimientosEditar = 'mantenimientos.editar';
    case MantenimientosEliminar = 'mantenimientos.eliminar';
    case MantenimientosVerTodos = 'mantenimientos.ver-todos';
    case MantenimientosIniciar = 'mantenimientos.iniciar';
    case MantenimientosFinalizar = 'mantenimientos.finalizar';
    case MantenimientosPdf = 'mantenimientos.pdf';
    case MantenimientosTerceros = 'mantenimientos.terceros';

    // Sin "crear": una calibración solo se origina desde "Agendar Calibraciones" (ver
    // OrdenTrabajoController), no tiene un formulario de creación propio.
    case CalibracionesVer = 'calibraciones.ver';
    case CalibracionesEditar = 'calibraciones.editar';
    case CalibracionesEliminar = 'calibraciones.eliminar';
    case CalibracionesVerTodos = 'calibraciones.ver-todos';
    case CalibracionesIniciar = 'calibraciones.iniciar';
    case CalibracionesFinalizar = 'calibraciones.finalizar';
    case CalibracionesPdf = 'calibraciones.pdf';
    case CalibracionesTerceros = 'calibraciones.terceros';

    // Sin "crear": un despacho solo se origina al finalizar una calibración (ver
    // CalibracionController::finalizar()), no tiene un formulario de creación propio.
    case DespachosVer = 'despachos.ver';
    case DespachosEditar = 'despachos.editar';
    case DespachosEliminar = 'despachos.eliminar';
    case DespachosVerTodos = 'despachos.ver-todos';

    case TiposEquipoVer = 'tipos-equipo.ver';
    case TiposEquipoCrear = 'tipos-equipo.crear';
    case TiposEquipoEditar = 'tipos-equipo.editar';
    case TiposEquipoEliminar = 'tipos-equipo.eliminar';
    case TiposEquipoChecklist = 'tipos-equipo.checklist';

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
    case ItemsRegistrarEntrada = 'items.registrar-entrada';
    case ItemsVerEntradas = 'items.ver-entradas';
    case ItemsRecibirEntradas = 'items.recibir-entradas';

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

    case AlcancesMedicionVer = 'alcances-medicion.ver';
    case AlcancesMedicionCrear = 'alcances-medicion.crear';
    case AlcancesMedicionEditar = 'alcances-medicion.editar';
    case AlcancesMedicionEliminar = 'alcances-medicion.eliminar';

    case NovedadesVer = 'novedades.ver';
    case NovedadesCrear = 'novedades.crear';
    case NovedadesEditar = 'novedades.editar';
    case NovedadesEliminar = 'novedades.eliminar';

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

    // Qué se puede importar desde un CSV (ver TipoImportacion): uno por tipo, con el valor
    // "importaciones.{tipo}".
    case ImportarEquipos = 'importaciones.equipos';
    case ImportarItems = 'importaciones.items';
    case ImportarClientes = 'importaciones.clientes';
    case ImportarFabricantes = 'importaciones.fabricantes';
    case ImportarProcedimientosCalibracion = 'importaciones.procedimientos-calibracion';
    case ImportarNovedades = 'importaciones.novedades';
    case ImportarLaboratorios = 'importaciones.laboratorios';
    case ImportarAreas = 'importaciones.areas';
    case ImportarBahias = 'importaciones.bahias';
    case ImportarEmpresasTerceras = 'importaciones.empresas-terceras';
    case ImportarTiposEquipo = 'importaciones.tipos-equipo';
    case ImportarChecklistMantenimiento = 'importaciones.checklist-mantenimiento';

    case RolesGestionar = 'roles.gestionar';
    case UsuariosGestionar = 'usuarios.gestionar';

    /**
     * Get the module the permission belongs to.
     */
    public function group(): string
    {
        return match ($this) {
            self::DashboardVer,
            self::DashboardTodosEquipos,
            self::DashboardEquiposAlDia,
            self::DashboardEquiposProximos,
            self::DashboardEquiposVencidos,
            self::DashboardEquiposUrgentes,
            self::DashboardTablaEquipos => 'Dashboard',
            self::AgendaVer,
            self::AgendaVerTodos,
            self::AgendarMantenimiento,
            self::AgendarCalibracion,
            self::AgendarDespachos => 'Agenda y agendamiento',
            self::ClientesVer,
            self::ClientesCrear,
            self::ClientesEditar,
            self::ClientesEliminar => 'Clientes',
            self::EquiposVer,
            self::EquiposCrear,
            self::EquiposEditar,
            self::EquiposEliminar,
            self::EquiposHistorial,
            self::EquiposProgramaciones => 'Equipos',
            self::IngresosVer,
            self::IngresosCrear,
            self::IngresosEditar,
            self::IngresosEliminar => 'Ingresos',
            self::MantenimientosVer,
            self::MantenimientosEditar,
            self::MantenimientosEliminar,
            self::MantenimientosVerTodos,
            self::MantenimientosIniciar,
            self::MantenimientosFinalizar,
            self::MantenimientosPdf,
            self::MantenimientosTerceros => 'Mantenimientos',
            self::CalibracionesVer,
            self::CalibracionesEditar,
            self::CalibracionesEliminar,
            self::CalibracionesVerTodos,
            self::CalibracionesIniciar,
            self::CalibracionesFinalizar,
            self::CalibracionesPdf,
            self::CalibracionesTerceros => 'Calibraciones',
            self::DespachosVer,
            self::DespachosEditar,
            self::DespachosEliminar,
            self::DespachosVerTodos => 'Despachos',
            self::TiposEquipoVer,
            self::TiposEquipoCrear,
            self::TiposEquipoEditar,
            self::TiposEquipoEliminar,
            self::TiposEquipoChecklist => 'Tipos de equipo',
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
            self::ItemsEliminar,
            self::ItemsRegistrarEntrada,
            self::ItemsVerEntradas,
            self::ItemsRecibirEntradas => 'Ítems',
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
            self::AlcancesMedicionVer,
            self::AlcancesMedicionCrear,
            self::AlcancesMedicionEditar,
            self::AlcancesMedicionEliminar => 'Alcances de medición',
            self::NovedadesVer,
            self::NovedadesCrear,
            self::NovedadesEditar,
            self::NovedadesEliminar => 'Novedades',
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
            self::ImportarEquipos,
            self::ImportarItems,
            self::ImportarClientes,
            self::ImportarFabricantes,
            self::ImportarProcedimientosCalibracion,
            self::ImportarNovedades,
            self::ImportarLaboratorios,
            self::ImportarAreas,
            self::ImportarBahias,
            self::ImportarEmpresasTerceras,
            self::ImportarTiposEquipo,
            self::ImportarChecklistMantenimiento => 'Importaciones',
            self::RolesGestionar => 'Roles',
            self::UsuariosGestionar => 'Usuarios',
        };
    }

    /**
     * Get the permissions that let a user see the services of every técnico.
     *
     * @return array<self>
     */
    public static function verTodosCases(): array
    {
        return [self::AgendaVerTodos, self::MantenimientosVerTodos, self::CalibracionesVerTodos, self::DespachosVerTodos];
    }

    /**
     * Get the permission that gives access to the servicios de terceros of the given tipo de servicio.
     */
    public static function tercerosDe(string $tipoServicio): self
    {
        return $tipoServicio === 'MANTENIMIENTO' ? self::MantenimientosTerceros : self::CalibracionesTerceros;
    }

    /**
     * Get every permission that belongs to the dashboard.
     *
     * @return array<self>
     */
    public static function dashboardCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $permission) => $permission->group() === 'Dashboard'));
    }

    /**
     * Determine if every role must hold the permission, so it cannot be unchecked.
     */
    public function isMandatory(): bool
    {
        return $this === self::DashboardVer;
    }

    /**
     * Get the display label for the permission.
     */
    public function label(): string
    {
        return match ($this) {
            self::DashboardVer => 'Ver el dashboard (obligatorio)',
            self::DashboardTodosEquipos => 'Tarjeta: todos los equipos',
            self::DashboardEquiposAlDia => 'Tarjeta: equipos al día',
            self::DashboardEquiposProximos => 'Tarjeta: equipos próximos a vencer',
            self::DashboardEquiposVencidos => 'Tarjeta: equipos vencidos',
            self::DashboardEquiposUrgentes => 'Listado de equipos urgentes',
            self::DashboardTablaEquipos => 'Tabla de equipos',
            self::AgendaVer => 'Ver la agenda',
            self::AgendaVerTodos => 'Ver la agenda de todos los técnicos (sin marcar, solo la propia)',
            self::AgendarMantenimiento => 'Agendar mantenimientos',
            self::AgendarCalibracion => 'Agendar calibraciones',
            self::AgendarDespachos => 'Agendar despachos',
            self::ClientesVer => 'Ver clientes',
            self::ClientesCrear => 'Crear clientes',
            self::ClientesEditar => 'Editar clientes',
            self::ClientesEliminar => 'Eliminar clientes',
            self::EquiposVer => 'Ver equipos',
            self::EquiposCrear => 'Crear equipos',
            self::EquiposEditar => 'Editar equipos',
            self::EquiposEliminar => 'Eliminar equipos',
            self::EquiposHistorial => 'Ver el historial de equipos',
            self::EquiposProgramaciones => 'Ver las programaciones de equipos',
            self::IngresosVer => 'Ver ingresos',
            self::IngresosCrear => 'Registrar ingresos',
            self::IngresosEditar => 'Editar ingresos',
            self::IngresosEliminar => 'Eliminar ingresos',
            self::MantenimientosVer => 'Ver mantenimientos',
            self::MantenimientosEditar => 'Editar mantenimientos',
            self::MantenimientosEliminar => 'Eliminar mantenimientos',
            self::MantenimientosVerTodos => 'Ver mantenimientos de todos los técnicos (sin marcar, solo los propios)',
            self::MantenimientosIniciar => 'Iniciar mantenimientos',
            self::MantenimientosFinalizar => 'Finalizar mantenimientos',
            self::MantenimientosPdf => 'Ver PDF de mantenimientos',
            self::MantenimientosTerceros => 'Mantenimientos de terceros',
            self::CalibracionesVer => 'Ver calibraciones',
            self::CalibracionesEditar => 'Editar calibraciones',
            self::CalibracionesEliminar => 'Eliminar calibraciones',
            self::CalibracionesVerTodos => 'Ver calibraciones de todos los técnicos (sin marcar, solo los propios)',
            self::CalibracionesIniciar => 'Iniciar calibraciones',
            self::CalibracionesFinalizar => 'Finalizar calibraciones',
            self::CalibracionesPdf => 'Ver PDF de calibraciones',
            self::CalibracionesTerceros => 'Calibraciones de terceros',
            self::DespachosVer => 'Ver despachos',
            self::DespachosEditar => 'Editar despachos',
            self::DespachosEliminar => 'Eliminar despachos',
            self::DespachosVerTodos => 'Ver despachos de todos los técnicos (sin marcar, solo los propios)',
            self::TiposEquipoVer => 'Ver tipos de equipo',
            self::TiposEquipoCrear => 'Crear tipos de equipo',
            self::TiposEquipoEditar => 'Editar tipos de equipo',
            self::TiposEquipoEliminar => 'Eliminar tipos de equipo',
            self::TiposEquipoChecklist => 'Agregar checklist a tipos de equipo',
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
            self::ItemsRegistrarEntrada => 'Registrar entradas de ítems',
            self::ItemsVerEntradas => 'Ver entradas y salidas de ítems',
            self::ItemsRecibirEntradas => 'Recibir entradas pendientes de ítems',
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
            self::AlcancesMedicionVer => 'Ver alcances de medición',
            self::AlcancesMedicionCrear => 'Crear alcances de medición',
            self::AlcancesMedicionEditar => 'Editar alcances de medición',
            self::AlcancesMedicionEliminar => 'Eliminar alcances de medición',
            self::NovedadesVer => 'Ver novedades',
            self::NovedadesCrear => 'Crear novedades',
            self::NovedadesEditar => 'Editar novedades',
            self::NovedadesEliminar => 'Eliminar novedades',
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
            self::ImportarEquipos => 'Importar equipos',
            self::ImportarItems => 'Importar ítems',
            self::ImportarClientes => 'Importar clientes',
            self::ImportarFabricantes => 'Importar fabricantes',
            self::ImportarProcedimientosCalibracion => 'Importar procedimientos de calibración',
            self::ImportarNovedades => 'Importar novedades',
            self::ImportarLaboratorios => 'Importar laboratorios',
            self::ImportarAreas => 'Importar áreas',
            self::ImportarBahias => 'Importar bahías',
            self::ImportarEmpresasTerceras => 'Importar empresas terceras',
            self::ImportarTiposEquipo => 'Importar tipos de equipo',
            self::ImportarChecklistMantenimiento => 'Importar checklist de mantenimiento',
            self::RolesGestionar => 'Gestionar roles',
            self::UsuariosGestionar => 'Gestionar usuarios',
        };
    }

    /**
     * Get every permission grouped by module, ready to be rendered as a matrix.
     *
     * @return array<int, array{group: string, permissions: array<int, array{value: string, label: string, mandatory: bool}>}>
     */
    public static function catalog(): array
    {
        return collect(self::cases())
            ->groupBy(fn (self $permission) => $permission->group())
            ->map(fn ($permissions, string $group) => [
                'group' => $group,
                'permissions' => $permissions
                    ->map(fn (self $permission) => ['value' => $permission->value, 'label' => $permission->label(), 'mandatory' => $permission->isMandatory()])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
