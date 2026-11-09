import { Check, Timer, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    formatearCantidad,
    formatearHoras,
    formatearPorcentaje,
} from '@/lib/indicadores';
import type { ResumenCumplimiento } from '@/lib/cumplimiento-responsable';
import { cn } from '@/lib/utils';

type Props = {
    titulo: string;
    icono: LucideIcon;
    /** Color de la barra: el del servicio (o el principal en el general). */
    colorBarra: string;
    resumen: ResumenCumplimiento;
    testId?: string;
};

/** Texto e icono de si cumple, en tinta de estado (nunca solo con color). */
function Estado({ cumple, texto }: { cumple: boolean; texto: string }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 font-medium',
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
            {texto}
        </span>
    );
}

/**
 * Cumplimiento de un servicio (o del general) frente a los objetivos: la cantidad como
 * barra de progreso, la suma de horas y si el tiempo promedio cumple.
 */
export default function TarjetaCumplimiento({
    titulo,
    icono: Icono,
    colorBarra,
    resumen,
    testId,
}: Props) {
    const { cantidad, tiempo } = resumen;
    const avance = cantidad === null ? 0 : Math.min(cantidad.porcentaje, 100);

    return (
        <div
            className="grid gap-3 rounded-xl border bg-card p-4"
            data-test={testId}
        >
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <Icono className="h-4 w-4 text-muted-foreground" />
                    {titulo}
                </div>
                <span
                    className="inline-flex items-center gap-1 text-xs text-muted-foreground tabular-nums"
                    title="Suma del tiempo registrado"
                >
                    <Timer className="h-3.5 w-3.5" />
                    {formatearHoras(resumen.horasTotales)}
                </span>
            </div>

            <div className="flex items-baseline justify-between gap-2">
                <span className="text-2xl font-bold tabular-nums">
                    {cantidad === null
                        ? '—'
                        : formatearPorcentaje(cantidad.porcentaje)}
                </span>
                <span className="text-xs text-muted-foreground tabular-nums">
                    {cantidad === null
                        ? `${resumen.realizados} sin objetivo`
                        : `${resumen.realizados} de ${formatearCantidad(cantidad.objetivo)}`}
                </span>
            </div>

            <div
                className="h-2 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-label={`Cumplimiento de ${titulo.toLowerCase()}`}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={Math.round(avance)}
            >
                <div
                    className="h-full rounded-full transition-[width]"
                    style={{ width: `${avance}%`, backgroundColor: colorBarra }}
                />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs">
                {cantidad === null ? (
                    <span className="text-muted-foreground">
                        Sin objetivo de cantidad
                    </span>
                ) : (
                    <Estado
                        cumple={cantidad.porcentaje >= 100}
                        texto={
                            cantidad.porcentaje >= 100
                                ? 'Cantidad cumplida'
                                : 'Cantidad pendiente'
                        }
                    />
                )}
                {tiempo === null ? (
                    <span className="text-muted-foreground">
                        Tiempo sin objetivo
                    </span>
                ) : (
                    <span className="inline-flex items-center gap-1">
                        <Estado
                            cumple={tiempo.cumple}
                            texto={formatearHoras(resumen.promedioHoras)}
                        />
                        <span className="text-muted-foreground">
                            / obj. {formatearHoras(tiempo.objetivo)}
                        </span>
                    </span>
                )}
            </div>
        </div>
    );
}
