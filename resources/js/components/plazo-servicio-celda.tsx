import { Badge } from '@/components/ui/badge';
import {
    BADGE_PLAZO,
    etiquetaPlazo,
    plazoServicio,
} from '@/lib/plazo-servicio';

type Props = {
    fechaProgramada: string | null;
    fechaRealizacion: string | null;
};

/**
 * Fecha programada de un servicio con su plazo (faltan N días, vence hoy o vencido, en
 * rojo) y, si ya se hizo, la fecha real y si fue a tiempo o tarde.
 */
export default function PlazoServicioCelda({
    fechaProgramada,
    fechaRealizacion,
}: Props) {
    const plazo = plazoServicio(fechaProgramada, fechaRealizacion);

    if (!fechaProgramada || !plazo) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="space-y-1" data-test="plazo-servicio">
            <p className="text-sm">{fechaProgramada}</p>
            <Badge className={BADGE_PLAZO[plazo.estado]}>
                {etiquetaPlazo(plazo)}
            </Badge>
            {fechaRealizacion ? (
                <p className="text-xs text-muted-foreground">
                    Realizado: {fechaRealizacion}
                </p>
            ) : null}
        </div>
    );
}
