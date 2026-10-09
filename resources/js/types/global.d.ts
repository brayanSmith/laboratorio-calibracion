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
            empresa: { nombre: string; logo_url: string | null } | null;
            sidebarOpen: boolean;
            pendientes: {
                mantenimientos: { local: number; tercero: number } | null;
                calibraciones: { local: number; tercero: number } | null;
                despachos: number | null;
                ingresos: number | null;
            } | null;
            [key: string]: unknown;
        };
    }
}
