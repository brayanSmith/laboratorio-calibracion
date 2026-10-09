import { Clock } from 'lucide-react';
import { useElapsedTime } from '@/hooks/use-elapsed-time';

type Props = {
    desde: string;
};

/** Reloj en vivo (HH:MM:SS) con el tiempo transcurrido desde que se inició un servicio. */
export default function TiempoTranscurrido({ desde }: Props) {
    const tiempo = useElapsedTime(new Date(desde));

    return (
        <span
            className="inline-flex items-center gap-1 font-mono text-sm text-muted-foreground"
            data-test="servicio-tercero-reloj"
        >
            <Clock className="h-3.5 w-3.5" />
            {tiempo}
        </span>
    );
}
