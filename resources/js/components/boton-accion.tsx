import type { LucideIcon } from 'lucide-react';
import { createContext, useContext } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * Cómo muestra su nombre el botón: en un globo (en la columna de acciones de la tabla) o
 * expandiéndose al pasar el cursor (en el menú de acciones que se abre al hacer clic en la
 * fila, donde sobra espacio a lo ancho).
 */
export type ModoBotonAccion = 'globo' | 'expandir';

const ModoBotonAccionContext = createContext<ModoBotonAccion>('globo');

export const ModoBotonAccionProvider = ModoBotonAccionContext.Provider;

type Props = Omit<ComponentProps<typeof Button>, 'children' | 'asChild'> & {
    icono: LucideIcon;
    /** Nombre de la acción: aparece al pasar el cursor y es el nombre accesible. */
    etiqueta: string;
    /** Si se indica, el botón es un enlace (por ejemplo, un PDF que abre en otra pestaña). */
    href?: string;
    target?: string;
    /** Dato siempre visible junto al icono (por ejemplo, un conteo). */
    insignia?: ReactNode;
};

/**
 * Botón de acción de las tablas: muestra solo el icono para ahorrar espacio y su nombre al
 * pasar el cursor o enfocarlo con el teclado (en un globo o expandiéndose, según el modo).
 */
export default function BotonAccion({
    icono: Icono,
    etiqueta,
    href,
    target,
    insignia,
    variant = 'ghost',
    size = 'sm',
    className,
    ...props
}: Props) {
    const modo = useContext(ModoBotonAccionContext);
    const expandir = modo === 'expandir';

    const contenido = (
        <>
            <Icono className="h-4 w-4" />
            {insignia ? (
                <span className="text-xs tabular-nums">{insignia}</span>
            ) : null}
            {expandir ? (
                <span className="max-w-0 overflow-hidden opacity-0 transition-all duration-200 ease-out group-hover/accion:max-w-40 group-hover/accion:opacity-100 group-focus-visible/accion:max-w-40 group-focus-visible/accion:opacity-100">
                    {etiqueta}
                </span>
            ) : null}
        </>
    );
    const clases = cn(
        expandir &&
            'group/accion gap-0 transition-all hover:gap-1.5 focus-visible:gap-1.5',
        className,
    );

    const boton = href ? (
        <Button
            variant={variant}
            size={size}
            className={clases}
            asChild
            {...props}
        >
            <a
                href={href}
                target={target}
                rel={target === '_blank' ? 'noreferrer' : undefined}
                aria-label={etiqueta}
            >
                {contenido}
            </a>
        </Button>
    ) : (
        <Button
            type="button"
            variant={variant}
            size={size}
            className={clases}
            aria-label={etiqueta}
            {...props}
        >
            {contenido}
        </Button>
    );

    if (expandir) {
        return boton;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>{boton}</TooltipTrigger>
            <TooltipContent>{etiqueta}</TooltipContent>
        </Tooltip>
    );
}
