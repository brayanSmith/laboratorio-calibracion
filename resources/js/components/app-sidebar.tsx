import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    FolderGit2,
    LayoutGrid,
    Wrench,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as equiposIndex } from '@/routes/equipos';
import { dashboard as platformDashboard } from '@/routes/plataforma';
import { index as tenantsIndex } from '@/routes/plataforma/tenants';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const isPlatformAdmin = page.props.auth.user.is_platform_admin === true;

    let dashboardUrl: NavItem['href'] = '/';

    if (isPlatformAdmin) {
        dashboardUrl = platformDashboard();
    } else if (page.props.currentTeam) {
        dashboardUrl = dashboard(page.props.currentTeam.slug);
    }

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
              {
                  title: 'Equipos',
                  href: equiposIndex(),
                  icon: Wrench,
              },
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
                {isPlatformAdmin ? null : (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <TeamSwitcher />
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
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
