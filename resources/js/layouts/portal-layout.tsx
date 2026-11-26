import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Monitor, UserRound } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { cuenta, logout } from '@/routes/portal';
import { index as equipos } from '@/routes/portal/equipos';

type PropsPortal = {
    empresa?: { nombre: string; logo_url: string | null } | null;
    cliente?: { nombre: string };
};

/**
 * Marco del portal de clientes: la marca del laboratorio, el nombre del cliente y su menú
 * (equipos, cuenta, salir). Va aparte del layout del laboratorio: el cliente no ve su menú.
 */
export default function PortalLayout({ children }: { children: ReactNode }) {
    const { empresa, cliente } = usePage().props as PropsPortal;
    const ruta = usePage().url;

    const enlaces = [
        { href: equipos().url, etiqueta: 'Mis equipos', icono: Monitor },
        { href: cuenta().url, etiqueta: 'Mi cuenta', icono: UserRound },
    ];

    return (
        <div className="min-h-svh bg-muted/30">
            <header className="border-b bg-background">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <Link
                        href={equipos().url}
                        className="flex min-w-0 items-center gap-3"
                    >
                        {empresa?.logo_url ? (
                            <img
                                src={empresa.logo_url}
                                alt={`Logo de ${empresa.nombre}`}
                                className="h-9 max-w-40 object-contain"
                            />
                        ) : null}
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold">
                                {empresa?.nombre ?? 'Portal de clientes'}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                Portal de clientes
                                {cliente ? ` · ${cliente.nombre}` : ''}
                            </p>
                        </div>
                    </Link>

                    <nav className="flex items-center gap-1">
                        {enlaces.map(({ href, etiqueta, icono: Icono }) => (
                            <Button
                                key={href}
                                variant="ghost"
                                size="sm"
                                asChild
                                className={cn(
                                    ruta.startsWith(href) &&
                                        'bg-muted font-semibold',
                                )}
                            >
                                <Link href={href}>
                                    <Icono className="size-4" />
                                    {etiqueta}
                                </Link>
                            </Button>
                        ))}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.post(logout().url)}
                            data-test="portal-logout"
                        >
                            <LogOut className="size-4" />
                            Salir
                        </Button>
                    </nav>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-4 py-6">{children}</main>
        </div>
    );
}
