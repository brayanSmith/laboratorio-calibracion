import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import BuscadorUniversal from '@/components/buscador-universal';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Spinner } from '@/components/ui/spinner';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useNavegacionPendiente } from '@/hooks/use-navegacion-pendiente';
import { toUrl } from '@/lib/utils';
import type { NavBadge, NavGroup } from '@/types';

const STORAGE_KEY = 'sidebar-collapsed-groups';

function readCollapsedGroups(): Record<string, boolean> {
    if (typeof window === 'undefined') {
        return {};
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        return raw ? (JSON.parse(raw) as Record<string, boolean>) : {};
    } catch {
        return {};
    }
}

function writeCollapsedGroups(value: Record<string, boolean>): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
    } catch {
        // Ignore write failures (private browsing, storage disabled, etc).
    }
}

/** Contadores a la derecha de un ítem del menú; los que valen 0 no se muestran. */
function NavItemBadges({ badges }: { badges?: NavBadge[] }) {
    const visibles = badges?.filter((badge) => badge.value > 0) ?? [];

    if (visibles.length === 0) {
        return null;
    }

    const total = visibles.reduce((suma, badge) => suma + badge.value, 0);

    return (
        <>
            <div className="pointer-events-none absolute top-1.5 right-1 flex items-center gap-1 group-data-[collapsible=icon]:hidden">
                {visibles.map((badge) => (
                    <span
                        key={badge.label}
                        title={badge.label}
                        aria-label={`${badge.label}: ${badge.value}`}
                        className={`flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-xs font-medium tabular-nums ${badge.className}`}
                        data-test="nav-badge"
                    >
                        {badge.value}
                    </span>
                ))}
            </div>
            {/* Con el menú contraído: un solo círculo en la esquina del icono con el total
                (del color del contador si es uno solo); el desglose va en el globo del ítem. */}
            <span
                aria-hidden="true"
                className={`pointer-events-none absolute top-0 right-0 hidden h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-none font-semibold tabular-nums ring-2 ring-sidebar group-data-[collapsible=icon]:flex ${visibles.length === 1 ? visibles[0].className : 'bg-red-500 text-white'}`}
                data-test="nav-badge-contraido"
            >
                {total > 99 ? '99+' : total}
            </span>
        </>
    );
}

/** Globo del ítem con el menú contraído: su nombre y, debajo, lo que tiene pendiente. */
function tooltipItem(item: NavGroup['items'][number]) {
    const pendientes = item.badges?.filter((badge) => badge.value > 0) ?? [];

    return {
        children:
            pendientes.length === 0 ? (
                item.title
            ) : (
                <div className="grid gap-0.5">
                    <span className="font-medium">{item.title}</span>
                    {pendientes.map((badge) => (
                        <span key={badge.label} className="opacity-90">
                            {badge.label}: {badge.value}
                        </span>
                    ))}
                </div>
            ),
    };
}

export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const rutaPendiente = useNavegacionPendiente();

    // El ítem del menú muestra un spinner mientras carga la página a la que lleva.
    const estaCargando = (href: NavGroup['items'][number]['href']): boolean =>
        rutaPendiente !== null &&
        new URL(toUrl(href), window.location.origin).pathname === rutaPendiente;
    const [collapsedGroups, setCollapsedGroups] = useState<
        Record<string, boolean>
    >({});

    useEffect(() => {
        setCollapsedGroups(readCollapsedGroups());
    }, []);

    const visibleGroups = groups.filter((group) => group.items.length > 0);

    const toggleGroup = (title: string) => {
        setCollapsedGroups((current) => {
            const next = { ...current, [title]: !current[title] };
            writeCollapsedGroups(next);

            return next;
        });
    };

    return (
        <>
            <SidebarGroup className="px-2 pt-1 pb-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <BuscadorUniversal groups={groups} />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            {visibleGroups.map((group, index) => {
                if (!group.title) {
                    return (
                        <SidebarGroup key={index} className="px-2 py-0">
                            <SidebarMenu>
                                {group.items.map((item) => (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={isCurrentUrl(item.href)}
                                            tooltip={tooltipItem(item)}
                                        >
                                            <Link
                                                href={item.href}
                                                prefetch
                                                aria-busy={
                                                    estaCargando(item.href) ||
                                                    undefined
                                                }
                                            >
                                                {estaCargando(item.href) ? (
                                                    <Spinner data-test="nav-item-cargando" />
                                                ) : (
                                                    item.icon && <item.icon />
                                                )}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                        <NavItemBadges badges={item.badges} />
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroup>
                    );
                }

                const isOpen = !collapsedGroups[group.title];

                return (
                    <Collapsible
                        key={group.title}
                        open={isOpen}
                        onOpenChange={() => toggleGroup(group.title as string)}
                        className="relative flex w-full min-w-0 flex-col px-2 py-0"
                    >
                        <SidebarGroupLabel asChild>
                            <CollapsibleTrigger className="w-full cursor-pointer justify-between">
                                <span>{group.title}</span>
                                <ChevronRight
                                    className={`h-4 w-4 shrink-0 transition-transform ${isOpen ? 'rotate-90' : ''}`}
                                />
                            </CollapsibleTrigger>
                        </SidebarGroupLabel>
                        <CollapsibleContent>
                            <SidebarMenu>
                                {group.items.map((item) => (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={isCurrentUrl(item.href)}
                                            tooltip={tooltipItem(item)}
                                        >
                                            <Link
                                                href={item.href}
                                                prefetch
                                                aria-busy={
                                                    estaCargando(item.href) ||
                                                    undefined
                                                }
                                            >
                                                {estaCargando(item.href) ? (
                                                    <Spinner data-test="nav-item-cargando" />
                                                ) : (
                                                    item.icon && <item.icon />
                                                )}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                        <NavItemBadges badges={item.badges} />
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </CollapsibleContent>
                    </Collapsible>
                );
            })}
        </>
    );
}
