import { Check, X } from 'lucide-react';
import { formatearPorcentaje } from '@/lib/indicadores';
import { cn } from '@/lib/utils';

type Props = {
    porcentaje: number;
    cumple: boolean;
    /** Texto pequeño debajo (ej. "de 15", "obj. 2 h"). */
    detalle?: string;
    testId?: string;
};

/**
 * Cumplimiento en porcentaje con una barra de progreso (llena al 100 %). El estado va
 * con icono y color, para que no dependa solo del color.
 */
export default function BarraCumplimiento({
    porcentaje,
    cumple,
    detalle,
    testId,
}: Props) {
    const avance = Math.max(0, Math.min(porcentaje, 100));

    return (
        <div className="grid min-w-28 gap-1" data-test={testId}>
            <div className="flex items-center justify-between gap-2">
                <span
                    className={cn(
                        'inline-flex items-center gap-1 text-xs font-medium tabular-nums',
                        cumple
                            ? 'text-emerald-700 dark:text-emerald-400'
                            : 'text-red-700 dark:text-red-400',
                    )}
                >
                    {cumple ? (
                        <Check className="h-3.5 w-3.5" />
                    ) : (
                        <X className="h-3.5 w-3.5" />
                    )}
                    {formatearPorcentaje(porcentaje)}
                </span>
                {detalle ? (
                    <span className="text-[11px] text-muted-foreground tabular-nums">
                        {detalle}
                    </span>
                ) : null}
            </div>
            <div
                className="h-1.5 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={Math.round(avance)}
            >
                <div
                    className={cn(
                        'h-full rounded-full',
                        cumple ? 'bg-emerald-500' : 'bg-red-500',
                    )}
                    style={{ width: `${avance}%` }}
                />
            </div>
        </div>
    );
}
