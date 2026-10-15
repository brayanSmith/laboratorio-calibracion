import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            plataforma: {
                nombre: string;
                logo_url: string | null;
            };
            empresa: {
                nombre: string;
                logo_url: string | null;
                color_tema: string | null;
                color_nav: string | null;
            } | null;
            sidebarOpen: boolean;
            pendientes: {
                mantenimientos: { local: number; tercero: number } | null;
                calibraciones: { local: number; tercero: number } | null;
                despachos: number | null;
                ingresos: number | null;
            } | null;
            /** Cuántos equipos y despachos están listos para agendar (null si no puede agendar). */
            porAgendar: {
                mantenimiento: number | null;
                calibracion: number | null;
                despachos: number | null;
            } | null;
            [key: string]: unknown;
        };
    }
}
