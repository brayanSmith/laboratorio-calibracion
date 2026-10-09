import { Link, usePage } from '@inertiajs/react';
import {
    Building,
    Building2,
    ClipboardCheck,
    ClipboardList,
    Contact,
    DoorOpen,
    Factory,
    FlaskConical,
    Gauge,
    Handshake,
    LayoutGrid,
    MapPin,
    Package,
    PackageCheck,
    Ruler,
    Scale,
    ShieldCheck,
    Sigma,
    Tags,
    TriangleAlert,
    Truck,
    Users,
    Wrench,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import { index as alcancesMedicionIndex } from '@/routes/alcances-medicion';
import { index as areasIndex } from '@/routes/areas';
import { index as bahiasIndex } from '@/routes/bahias';
import { index as calibracionesIndex } from '@/routes/calibraciones';
import { index as clientesIndex } from '@/routes/clientes';
import { index as despachosIndex } from '@/routes/despachos';
import { index as empresasTercerasIndex } from '@/routes/empresas-terceras';
import { index as equiposIndex } from '@/routes/equipos';
import { index as fabricantesIndex } from '@/routes/fabricantes';
import { index as ingresosIndex } from '@/routes/ingresos';
import { index as itemsIndex } from '@/routes/items';
import { index as laboratoriosIndex } from '@/routes/laboratorios';
import { index as mantenimientosIndex } from '@/routes/mantenimientos';
import { dashboard as platformDashboard } from '@/routes/plataforma';
import { index as tenantsIndex } from '@/routes/plataforma/tenants';
import { show as empresaShow } from '@/routes/empresa';
import { index as novedadesIndex } from '@/routes/novedades';
import { index as procedimientosCalibracionIndex } from '@/routes/procedimientos-calibracion';
import { index as rolesIndex } from '@/routes/roles';
import { index as tiposEquipoIndex } from '@/routes/tipos-equipo';
import { index as tiposMagnitudIndex } from '@/routes/tipos-magnitud';
import { index as unidadesMedidaIndex } from '@/routes/unidades-medida';
import { index as usuariosIndex } from '@/routes/usuarios';
import type { NavGroup, NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const isPlatformAdmin = page.props.auth.user.is_platform_admin === true;
    const { can } = usePermissions();

    const dashboardUrl: NavItem['href'] = isPlatformAdmin
        ? platformDashboard()
        : dashboard();

    const mainNavGroups: NavGroup[] = isPlatformAdmin
        ? [
              {
                  items: [
                      {
                          title: 'Dashboard',
                          href: dashboardUrl,
                          icon: LayoutGrid,
                      },
                      {
                          title: 'Tenants',
                          href: tenantsIndex(),
                          icon: Building2,
                      },
                  ],
              },
          ]
        : [
              {
                  items: [
                      {
                          title: 'Dashboard',
                          href: dashboardUrl,
                          icon: LayoutGrid,
                      },
                      ...(can('empresa.ver')
                          ? [
                                {
                                    title: 'Mi empresa',
                                    href: empresaShow(),
                                    icon: Building,
                                },
                            ]
                          : []),
                  ],
              },
              {
                  title: 'Operación',
                  items: [
                      ...(can('equipos.ver')
                          ? [
                                {
                                    title: 'Equipos',
                                    href: equiposIndex(),
                                    icon: Wrench,
                                },
                            ]
                          : []),
                      ...(can('ingresos.ver')
                          ? [
                                {
                                    title: 'Ingresos',
                                    href: ingresosIndex(),
                                    icon: PackageCheck,
                                    badges: [
                                        {
                                            label: 'Ingresos pendientes',
                                            value:
                                                page.props.pendientes
                                                    ?.ingresos ?? 0,
                                            className: 'bg-rose-500 text-white',
                                        },
                                    ],
                                },
                            ]
                          : []),
                      ...(can('mantenimientos.ver')
                          ? [
                                {
                                    title: 'Mantenimientos',
                                    href: mantenimientosIndex(),
                                    icon: ClipboardCheck,
                                    badges: [
                                        {
                                            label: 'Pendientes en local',
                                            value:
                                                page.props.pendientes
                                                    ?.mantenimientos?.local ??
                                                0,
                                            className: 'bg-blue-500 text-white',
                                        },
                                        {
                                            label: 'Pendientes en tercero',
                                            value:
                                                page.props.pendientes
                                                    ?.mantenimientos?.tercero ??
                                                0,
                                            className:
                                                'bg-amber-500 text-white',
                                        },
                                    ],
                                },
                            ]
                          : []),
                      ...(can('calibraciones.ver')
                          ? [
                                {
                                    title: 'Calibraciones',
                                    href: calibracionesIndex(),
                                    icon: Gauge,
                                    badges: [
                                        {
                                            label: 'Pendientes en local',
                                            value:
                                                page.props.pendientes
                                                    ?.calibraciones?.local ?? 0,
                                            className: 'bg-blue-500 text-white',
                                        },
                                        {
                                            label: 'Pendientes en tercero',
                                            value:
                                                page.props.pendientes
                                                    ?.calibraciones?.tercero ??
                                                0,
                                            className:
                                                'bg-amber-500 text-white',
                                        },
                                    ],
                                },
                            ]
                          : []),
                      ...(can('despachos.ver')
                          ? [
                                {
                                    title: 'Despachos',
                                    href: despachosIndex(),
                                    icon: Truck,
                                    badges: [
                                        {
                                            label: 'Entregas pendientes',
                                            value:
                                                page.props.pendientes
                                                    ?.despachos ?? 0,
                                            className:
                                                'bg-emerald-500 text-white',
                                        },
                                    ],
                                },
                            ]
                          : []),
                      ...(can('clientes.ver')
                          ? [
                                {
                                    title: 'Clientes',
                                    href: clientesIndex(),
                                    icon: Contact,
                                },
                            ]
                          : []),
                      ...(can('areas.ver')
                          ? [
                                {
                                    title: 'Áreas',
                                    href: areasIndex(),
                                    icon: MapPin,
                                },
                            ]
                          : []),
                      ...(can('bahias.ver')
                          ? [
                                {
                                    title: 'Bahías',
                                    href: bahiasIndex(),
                                    icon: DoorOpen,
                                },
                            ]
                          : []),
                      ...(can('laboratorios.ver')
                          ? [
                                {
                                    title: 'Laboratorios',
                                    href: laboratoriosIndex(),
                                    icon: FlaskConical,
                                },
                            ]
                          : []),
                      ...(can('empresas-terceras.ver')
                          ? [
                                {
                                    title: 'Empresas terceras',
                                    href: empresasTercerasIndex(),
                                    icon: Handshake,
                                },
                            ]
                          : []),
                  ],
              },
              {
                  title: 'Catálogos',
                  items: [
                      ...(can('fabricantes.ver')
                          ? [
                                {
                                    title: 'Fabricantes',
                                    href: fabricantesIndex(),
                                    icon: Factory,
                                },
                            ]
                          : []),
                      ...(can('items.ver')
                          ? [
                                {
                                    title: 'Ítems',
                                    href: itemsIndex(),
                                    icon: Package,
                                },
                            ]
                          : []),
                      ...(can('tipos-equipo.ver')
                          ? [
                                {
                                    title: 'Tipos de equipo',
                                    href: tiposEquipoIndex(),
                                    icon: Tags,
                                },
                            ]
                          : []),
                      ...(can('procedimientos-calibracion.ver')
                          ? [
                                {
                                    title: 'Procedimientos de calibración',
                                    href: procedimientosCalibracionIndex(),
                                    icon: ClipboardList,
                                },
                            ]
                          : []),
                      ...(can('alcances-medicion.ver')
                          ? [
                                {
                                    title: 'Alcances de medición',
                                    href: alcancesMedicionIndex(),
                                    icon: Scale,
                                },
                            ]
                          : []),
                      ...(can('novedades.ver')
                          ? [
                                {
                                    title: 'Novedades',
                                    href: novedadesIndex(),
                                    icon: TriangleAlert,
                                },
                            ]
                          : []),
                      ...(can('tipos-magnitud.ver')
                          ? [
                                {
                                    title: 'Tipos de magnitud',
                                    href: tiposMagnitudIndex(),
                                    icon: Sigma,
                                },
                            ]
                          : []),
                      ...(can('unidades-medida.ver')
                          ? [
                                {
                                    title: 'Unidades de medida',
                                    href: unidadesMedidaIndex(),
                                    icon: Ruler,
                                },
                            ]
                          : []),
                  ],
              },
              {
                  title: 'Administración',
                  items: [
                      ...(can('usuarios.gestionar')
                          ? [
                                {
                                    title: 'Usuarios',
                                    href: usuariosIndex(),
                                    icon: Users,
                                },
                            ]
                          : []),
                      ...(can('roles.gestionar')
                          ? [
                                {
                                    title: 'Roles',
                                    href: rolesIndex(),
                                    icon: ShieldCheck,
                                },
                            ]
                          : []),
                  ],
              },
          ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={mainNavGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
