import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Props = {
    mensaje?: string;
    className?: string;
};

/**
 * Indicador de carga para el contenido que se pide al servidor (por ejemplo,
 * los listados de las modales) y así el usuario sabe que el sistema sigue trabajando.
 */
export default function CargandoContenido({
    mensaje = 'Cargando información...',
    className,
}: Props) {
    return (
        <div
            role="status"
            aria-live="polite"
            className={cn(
                'flex items-center justify-center gap-2 py-8 text-sm text-muted-foreground',
                className,
            )}
            data-test="cargando-contenido"
        >
            <Spinner aria-hidden="true" />
            <span>{mensaje}</span>
        </div>
    );
}
