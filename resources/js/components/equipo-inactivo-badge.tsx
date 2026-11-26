import { Power } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * Clases de una fila del historial cuyo equipo quedó inactivo: se sigue viendo (el servicio
 * se hizo), pero tachada y atenuada para distinguirla de un vistazo. La etiqueta
 * EquipoInactivoBadge (inline-flex) no hereda el tachado, así que se lee limpia.
 */
export const CLASE_FILA_EQUIPO_INACTIVO =
    'bg-muted/50 text-muted-foreground [&>td]:line-through [&>td]:decoration-muted-foreground/70';

/** Etiqueta que acompaña al equipo cuando está inactivo, en el historial de servicios. */
export default function EquipoInactivoBadge({
    className,
}: {
    className?: string;
}) {
    return (
        <Badge
            className={cn(
                'gap-1 !border-transparent !bg-slate-500 !text-white',
                className,
            )}
            title="El equipo está inactivo: no cuenta en el dashboard ni en lo pendiente"
            data-test="equipo-inactivo-badge"
        >
            <Power className="size-3" />
            Equipo inactivo
        </Badge>
    );
}
