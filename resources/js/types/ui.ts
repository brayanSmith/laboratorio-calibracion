import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
    /** Registros que no se pudieron eliminar y qué los tiene en uso (ver EliminaPorLote). */
    detalle?: DetalleNoEliminables;
};

/** Grupo de registros que tienen en uso a otro (ej. "Mantenimientos": OT-0012, OT-0015...). */
export type DependenciaRegistro = {
    titulo: string;
    /** Cuántos hay; `registros` trae solo los primeros. */
    total: number;
    registros: string[];
};

export type DetalleNoEliminables = {
    registros: {
        nombre: string;
        motivo: string;
        dependencias: DependenciaRegistro[];
    }[];
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
