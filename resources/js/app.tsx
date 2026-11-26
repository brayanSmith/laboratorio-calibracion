import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PortalLayout from '@/layouts/portal-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { escapeHtml } from '@/lib/escape-html';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title, page) => {
        // Inertia inserts this result as HTML, so the name must be escaped.
        // En el seguimiento público, la empresa es la del ingreso consultado.
        const props = page.props as {
            empresa?: { nombre: string } | null;
            plataforma?: { nombre: string } | null;
            ingreso?: { empresa?: { nombre: string } | null } | null;
        };
        const brand = escapeHtml(
            props.empresa?.nombre ??
                props.ingreso?.empresa?.nombre ??
                props.plataforma?.nombre ??
                appName,
        );

        return title ? `${title} - ${brand}` : brand;
    },
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name.startsWith('seguimiento/'):
                return null;
            case name.startsWith('auth/'):
            case name.startsWith('portal/auth/'):
                return AuthLayout;
            // El portal de clientes, con su propio marco (sin el menú del laboratorio).
            case name.startsWith('portal/'):
                return PortalLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
        showSpinner: true,
    },
});

// This will set light / dark mode on load...
initializeTheme();
