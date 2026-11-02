import type { LucideIcon } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = Omit<ComponentProps<typeof Button>, 'children' | 'asChild'> & {
    icono: LucideIcon;
    /** Nombre de la acción: se despliega al pasar el cursor y es el nombre accesible. */
    etiqueta: string;
    /** Si se indica, el botón es un enlace (por ejemplo, un PDF que abre en otra pestaña). */
    href?: string;
    target?: string;
    /** Dato siempre visible junto al icono (por ejemplo, un conteo). */
    insignia?: ReactNode;
};

/**
 * Botón de acción de las tablas: muestra solo el icono para ahorrar espacio y, al pasar
 * el cursor o enfocarlo con el teclado, se expande con su nombre.
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
    const contenido = (
        <>
            <Icono className="h-4 w-4" />
            {insignia ? (
                <span className="ml-1 text-xs tabular-nums">{insignia}</span>
            ) : null}
            <span className="max-w-0 overflow-hidden opacity-0 transition-all duration-200 ease-out group-hover/accion:max-w-40 group-hover/accion:opacity-100 group-focus-visible/accion:max-w-40 group-focus-visible/accion:opacity-100">
                {etiqueta}
            </span>
        </>
    );
    const clases = cn(
        'group/accion gap-0 transition-all hover:gap-1.5 focus-visible:gap-1.5',
        className,
    );

    if (href) {
        return (
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
        );
    }

    return (
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
}
