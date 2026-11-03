import type { LucideIcon } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type Props = Omit<ComponentProps<typeof Button>, 'children' | 'asChild'> & {
    icono: LucideIcon;
    /** Nombre de la acción: aparece en un globo al pasar el cursor y es el nombre accesible. */
    etiqueta: string;
    /** Si se indica, el botón es un enlace (por ejemplo, un PDF que abre en otra pestaña). */
    href?: string;
    target?: string;
    /** Dato siempre visible junto al icono (por ejemplo, un conteo). */
    insignia?: ReactNode;
};

/**
 * Botón de acción de las tablas: muestra solo el icono para ahorrar espacio y su nombre
 * en un globo al pasar el cursor o enfocarlo con el teclado.
 */
export default function BotonAccion({
    icono: Icono,
    etiqueta,
    href,
    target,
    insignia,
    variant = 'ghost',
    size = 'sm',
    ...props
}: Props) {
    const contenido = (
        <>
            <Icono className="h-4 w-4" />
            {insignia ? (
                <span className="text-xs tabular-nums">{insignia}</span>
            ) : null}
        </>
    );

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                {href ? (
                    <Button variant={variant} size={size} asChild {...props}>
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
                        aria-label={etiqueta}
                        {...props}
                    >
                        {contenido}
                    </Button>
                )}
            </TooltipTrigger>
            <TooltipContent>{etiqueta}</TooltipContent>
        </Tooltip>
    );
}
