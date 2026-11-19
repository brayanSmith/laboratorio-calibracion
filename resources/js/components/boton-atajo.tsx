import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

/** Globito con el conteo sobre el icono, oculto si no hay nada pendiente. */
function ConteoBubble({ count }: { count: number }) {
    if (count === 0) {
        return null;
    }

    return (
        <span
            className="pointer-events-none absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-medium text-white ring-2 ring-background md:-top-3 md:-right-3.5 md:ring-0"
            data-test="agendar-conteo-bubble"
        >
            {count > 99 ? '99+' : count}
        </span>
    );
}

/**
 * Botón de un atajo. En escritorio es un icono del menú superior que muestra su título al
 * pasar el mouse. En móvil es una pestaña de la barra inferior, con `etiqueta` (corta)
 * debajo del icono; con `activo` (su ventana está abierta o es la página actual) el icono
 * sube en un círculo de color, sobresaliendo de la barra. Con `href` es un enlace a esa
 * página en vez de un botón; con `soloMovil` solo aparece en la barra inferior. Con
 * `cargando` el icono cambia por un spinner (por ejemplo, mientras corre una importación).
 */
export default function BotonAtajo({
    icono: Icono,
    titulo,
    etiqueta,
    cantidad = 0,
    cargando = false,
    activo = false,
    soloMovil = false,
    dataTest,
    href,
    onClick,
    children,
}: {
    icono: LucideIcon;
    titulo: string;
    /** Nombre corto para la barra inferior del móvil; por defecto, el título. */
    etiqueta?: string;
    cantidad?: number;
    cargando?: boolean;
    activo?: boolean;
    soloMovil?: boolean;
    dataTest: string;
    href?: string;
    onClick?: () => void;
    /** Algo pegado al botón (dentro de su caja), ej. el ancla de un popover. */
    children?: ReactNode;
}) {
    const nombre = cantidad > 0 ? `${titulo} (${cantidad} pendientes)` : titulo;
    const contenido = (
        <>
            <span
                className={cn(
                    'relative flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-all duration-300 ease-out motion-reduce:transition-none',
                    'md:mt-0 md:size-auto md:translate-y-0 md:bg-transparent md:text-current md:shadow-none md:ring-0',
                    activo &&
                        '-mt-3 size-12 -translate-y-4 bg-primary text-primary-foreground shadow-lg ring-[5px] ring-background',
                )}
            >
                {cargando ? (
                    <Spinner className="size-5" />
                ) : (
                    <Icono className="size-5" />
                )}
                <ConteoBubble count={cantidad} />
            </span>
            <span
                aria-hidden="true"
                className={cn(
                    'block max-w-full overflow-hidden text-[10.5px] leading-4 font-medium tracking-tight text-ellipsis whitespace-nowrap transition-colors md:hidden',
                    activo ? 'text-foreground' : 'text-muted-foreground',
                )}
            >
                {etiqueta ?? titulo}
            </span>
            <span
                aria-hidden="true"
                className="hidden max-w-0 overflow-hidden text-sm font-medium whitespace-nowrap opacity-0 transition-all duration-300 ease-out group-hover/atajo:ml-2 group-hover/atajo:max-w-48 group-hover/atajo:opacity-100 group-focus-visible/atajo:ml-2 group-focus-visible/atajo:max-w-48 group-focus-visible/atajo:opacity-100 motion-reduce:transition-none md:inline"
            >
                {titulo}
            </span>
        </>
    );
    const clases =
        'group/atajo h-auto min-h-14 w-full min-w-0 flex-col justify-end gap-1 overflow-visible px-0.5 pt-1 pb-0.5 hover:bg-transparent md:h-9 md:min-h-0 md:w-auto md:flex-row md:justify-center md:gap-0 md:px-2.5 md:py-0 md:hover:bg-accent';

    return (
        <div
            className={cn(
                'relative min-w-[4.5rem] flex-1 shrink-0 snap-start md:min-w-0 md:flex-none',
                soloMovil && 'md:hidden',
            )}
        >
            {href ? (
                <Button variant="ghost" className={clases} asChild>
                    <Link
                        href={href}
                        aria-label={nombre}
                        aria-current={activo ? 'page' : undefined}
                        data-test={dataTest}
                    >
                        {contenido}
                    </Link>
                </Button>
            ) : (
                <Button
                    type="button"
                    variant="ghost"
                    aria-label={nombre}
                    aria-busy={cargando || undefined}
                    aria-pressed={activo}
                    onClick={onClick}
                    data-test={dataTest}
                    className={clases}
                >
                    {contenido}
                </Button>
            )}
            {children}
        </div>
    );
}
