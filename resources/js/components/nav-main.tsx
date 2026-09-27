import { Link } from '@inertiajs/react';
import { ChevronRight, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarInput,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { normalizeText } from '@/lib/data-table';
import type { NavGroup } from '@/types';

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

export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const [query, setQuery] = useState('');
    const [collapsedGroups, setCollapsedGroups] = useState<
        Record<string, boolean>
    >({});

    useEffect(() => {
        setCollapsedGroups(readCollapsedGroups());
    }, []);

    const isSearching = query.trim() !== '';

    const filteredGroups = useMemo(() => {
        if (!isSearching) {
            return groups;
        }

        const needle = normalizeText(query);

        return groups.map((group) => ({
            ...group,
            items: group.items.filter((item) =>
                normalizeText(item.title).includes(needle),
            ),
        }));
    }, [groups, query, isSearching]);

    const visibleGroups = filteredGroups.filter(
        (group) => group.items.length > 0,
    );

    const toggleGroup = (title: string) => {
        setCollapsedGroups((current) => {
            const next = { ...current, [title]: !current[title] };
            writeCollapsedGroups(next);

            return next;
        });
    };

    return (
        <>
            <div className="px-2 pt-1 pb-2 group-data-[collapsible=icon]:hidden">
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2 text-sidebar-foreground/50" />
                    <SidebarInput
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Buscar módulo..."
                        aria-label="Buscar módulo"
                        className="pl-8"
                        data-test="nav-search"
                    />
                </div>
            </div>

            {visibleGroups.length === 0 ? (
                <p className="px-4 py-2 text-sm text-sidebar-foreground/50 group-data-[collapsible=icon]:hidden">
                    No se encontró ningún módulo.
                </p>
            ) : null}

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
                                            tooltip={{ children: item.title }}
                                        >
                                            <Link href={item.href} prefetch>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroup>
                    );
                }

                const isOpen = isSearching || !collapsedGroups[group.title];

                return (
                    <Collapsible
                        key={group.title}
                        open={isOpen}
                        onOpenChange={() =>
                            !isSearching && toggleGroup(group.title as string)
                        }
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
                                            tooltip={{ children: item.title }}
                                        >
                                            <Link href={item.href} prefetch>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
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
