import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    FolderGit2,
    LayoutGrid,
    ShieldCheck,
    Users,
    Wrench,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import { index as equiposIndex } from '@/routes/equipos';
import { dashboard as platformDashboard } from '@/routes/plataforma';
import { index as tenantsIndex } from '@/routes/plataforma/tenants';
import { index as rolesIndex } from '@/routes/roles';
import { index as usuariosIndex } from '@/routes/usuarios';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const isPlatformAdmin = page.props.auth.user.is_platform_admin === true;
    const { can } = usePermissions();

    const dashboardUrl: NavItem['href'] = isPlatformAdmin
        ? platformDashboard()
        : dashboard();

    const mainNavItems: NavItem[] = isPlatformAdmin
        ? [
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
          ]
        : [
              {
                  title: 'Dashboard',
                  href: dashboardUrl,
                  icon: LayoutGrid,
              },
              ...(can('equipos.ver')
                  ? [
                        {
                            title: 'Equipos',
                            href: equiposIndex(),
                            icon: Wrench,
                        },
                    ]
                  : []),
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
          ];

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
