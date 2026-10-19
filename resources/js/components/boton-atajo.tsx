import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/** Globito con el conteo sobre un icono, oculto si no hay nada pendiente. */
function ConteoBubble({ count }: { count: number }) {
    if (count === 0) {
        return null;
    }

    return (
        <span
            className="pointer-events-none absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-medium text-white"
            data-test="agendar-conteo-bubble"
        >
            {count > 99 ? '99+' : count}
        </span>
    );
}

/**
 * Botón de icono del menú superior que muestra su título al pasar el mouse. Con `cargando`
 * el icono cambia por un spinner (por ejemplo, mientras corre una importación).
 */
export default function BotonAtajo({
    icono: Icono,
    titulo,
    cantidad = 0,
    cargando = false,
    dataTest,
    onClick,
}: {
    icono: LucideIcon;
    titulo: string;
    cantidad?: number;
    cargando?: boolean;
    dataTest: string;
    onClick: () => void;
}) {
    return (
        <div className="relative">
            <Button
                type="button"
                variant="ghost"
                aria-label={
                    cantidad > 0 ? `${titulo} (${cantidad} pendientes)` : titulo
                }
                aria-busy={cargando || undefined}
                onClick={onClick}
                data-test={dataTest}
                className="group/atajo h-9 gap-0 overflow-hidden px-2.5"
            >
                {cargando ? (
                    <Spinner className="size-5 shrink-0" />
                ) : (
                    <Icono className="size-5 shrink-0" />
                )}
                <span
                    aria-hidden="true"
                    className="max-w-0 overflow-hidden text-sm font-medium whitespace-nowrap opacity-0 transition-all duration-300 ease-out group-hover/atajo:ml-2 group-hover/atajo:max-w-48 group-hover/atajo:opacity-100 group-focus-visible/atajo:ml-2 group-focus-visible/atajo:max-w-48 group-focus-visible/atajo:opacity-100 motion-reduce:transition-none"
                >
                    {titulo}
                </span>
            </Button>
            <ConteoBubble count={cantidad} />
        </div>
    );
}
