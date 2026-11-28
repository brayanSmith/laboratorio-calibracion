import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Monitor, UserRound } from 'lucide-react';
import type { ReactNode } from 'react';
import SelectorTema from '@/components/portal/selector-tema';
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
 * (equipos, cuenta, tema, salir). Va aparte del layout del laboratorio: el cliente no ve su
 * menú. En el celular el menú baja a una barra fija abajo, al alcance del pulgar.
 */
export default function PortalLayout({ children }: { children: ReactNode }) {
    const { empresa, cliente } = usePage().props as PropsPortal;
    const ruta = usePage().url;

    const enlaces = [
        { href: equipos().url, etiqueta: 'Mis equipos', icono: Monitor },
        { href: cuenta().url, etiqueta: 'Mi cuenta', icono: UserRound },
    ];

    const salir = () => router.post(logout().url);

    return (
        <div className="min-h-svh bg-muted/30">
            <header className="sticky top-0 z-30 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3">
                    <Link
                        href={equipos().url}
                        className="flex min-w-0 items-center gap-3"
                    >
                        {empresa?.logo_url ? (
                            <img
                                src={empresa.logo_url}
                                alt={`Logo de ${empresa.nombre}`}
                                className="h-8 max-w-28 shrink-0 object-contain sm:h-9 sm:max-w-40"
                            />
                        ) : null}
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold">
                                {empresa?.nombre ?? 'Portal de clientes'}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {cliente?.nombre ?? 'Portal de clientes'}
                            </p>
                        </div>
                    </Link>

                    {/* En el celular solo el tema arriba; el resto va en la barra de abajo. */}
                    <div className="sm:hidden">
                        <SelectorTema />
                    </div>

                    <nav className="hidden items-center gap-1 sm:flex">
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
                        <SelectorTema />
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={salir}
                            data-test="portal-logout"
                        >
                            <LogOut className="size-4" />
                            Salir
                        </Button>
                    </nav>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-4 pt-5 pb-28 sm:py-6">
                {children}
            </main>

            <nav
                className="fixed inset-x-0 bottom-0 z-30 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur supports-[backdrop-filter]:bg-background/85 sm:hidden"
                data-test="portal-nav-movil"
            >
                <div className="grid grid-cols-3">
                    {enlaces.map(({ href, etiqueta, icono: Icono }) => {
                        const activo = ruta.startsWith(href);

                        return (
                            <Link
                                key={href}
                                href={href}
                                className={cn(
                                    'flex flex-col items-center gap-1 py-2.5 text-xs font-medium text-muted-foreground',
                                    activo && 'text-primary',
                                )}
                            >
                                <Icono
                                    className={cn(
                                        'size-5',
                                        activo && 'stroke-[2.5]',
                                    )}
                                />
                                {etiqueta}
                            </Link>
                        );
                    })}
                    <button
                        type="button"
                        onClick={salir}
                        className="flex flex-col items-center gap-1 py-2.5 text-xs font-medium text-muted-foreground"
                    >
                        <LogOut className="size-5" />
                        Salir
                    </button>
                </div>
            </nav>
        </div>
    );
}
