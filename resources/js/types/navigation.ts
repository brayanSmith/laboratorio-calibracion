import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Contadores que se muestran a la derecha del ítem; los que valen 0 se ocultan. */
    badges?: NavBadge[];
};

export type NavBadge = {
    label: string;
    value: number;
    className: string;
};

export type NavGroup = {
    title?: string;
    items: NavItem[];
};
