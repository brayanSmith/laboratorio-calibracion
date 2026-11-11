import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Color del ícono de un paso: bien (verde), aviso (ámbar), mal (rojo) o neutro. */
export type TonoPaso = 'neutro' | 'malo' | 'bueno' | 'aviso';

/** Línea de tiempo vertical: los pasos van uno debajo del otro, unidos por una línea. */
export function LineaDeTiempo({
    children,
    testId,
}: {
    children: ReactNode;
    testId?: string;
}) {
    return (
        <ol className="ml-3 space-y-5 border-l pl-6" data-test={testId}>
            {children}
        </ol>
    );
}

/** Un paso de la línea de tiempo, con su ícono a la izquierda. */
export function Paso({
    icono: Icono,
    titulo,
    tono = 'neutro',
    children,
    testId = 'traza-paso',
}: {
    icono: LucideIcon;
    titulo: ReactNode;
    tono?: TonoPaso;
    children?: ReactNode;
    testId?: string;
}) {
    return (
        <li className="relative space-y-1" data-test={testId}>
            <span
                className={cn(
                    'absolute top-0 -left-[1.95rem] flex size-6 items-center justify-center rounded-full border bg-background',
                    tono === 'malo' && 'border-red-300 text-red-600',
                    tono === 'bueno' && 'border-emerald-300 text-emerald-600',
                    tono === 'aviso' && 'border-amber-300 text-amber-600',
                    tono === 'neutro' && 'text-muted-foreground',
                )}
            >
                <Icono className="size-3.5" />
            </span>
            <div className="text-sm font-medium">{titulo}</div>
            {children ? (
                <div className="space-y-1 text-sm text-muted-foreground">
                    {children}
                </div>
            ) : null}
        </li>
    );
}
