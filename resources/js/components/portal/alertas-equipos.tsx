import { Link } from '@inertiajs/react';
import { ChevronDown, CircleCheck, Siren } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { formatearFecha } from '@/lib/fechas';
import { cn } from '@/lib/utils';
import { show } from '@/routes/portal/equipos';
import type { EquipoPortal } from '@/types';

type EstadoAlerta = 'VENCIDO' | 'PROXIMO_A_VENCER';

/** Cada alerta coloreada: roja si el equipo está vencido, ámbar si está por vencer. */
const ESTILO: Record<
    EstadoAlerta,
    { fila: string; boton: string; badge: string; etiqueta: string }
> = {
    VENCIDO: {
        fila: 'border-l-red-600 bg-red-100/70 dark:bg-red-950/40',
        boton: 'bg-red-700 text-white hover:bg-red-800',
        badge: '!border-transparent !bg-red-500 !text-white',
        etiqueta: 'Vencido',
    },
    PROXIMO_A_VENCER: {
        fila: 'border-l-amber-500 bg-amber-100/70 dark:bg-amber-950/40',
        boton: 'bg-amber-600 text-white hover:bg-amber-700',
        badge: '!border-transparent !bg-amber-500 !text-white',
        etiqueta: 'Próximo a vencer',
    },
};

/** Días que faltan (positivo) o que pasaron (negativo) hasta la fecha `YYYY-MM-DD`. */
function diasHasta(fecha: string): number {
    const [anio, mes, dia] = fecha.slice(0, 10).split('-').map(Number);
    const objetivo = new Date(anio, mes - 1, dia);
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);

    return Math.round((objetivo.getTime() - hoy.getTime()) / 86_400_000);
}

/** "Venció hace 3 días", "Vence hoy", "Vence en 5 días"... */
function textoPlazo(fecha: string): string {
    const dias = diasHasta(fecha);

    if (dias < 0) {
        return `Venció hace ${-dias} ${-dias === 1 ? 'día' : 'días'}`;
    }

    if (dias === 0) {
        return 'Vence hoy';
    }

    return `Vence en ${dias} ${dias === 1 ? 'día' : 'días'}`;
}

/**
 * Alertas del portal: los equipos vencidos y los próximos a vencer del cliente, primero
 * los vencidos y luego por fecha, para que esté pendiente de enviarlos al laboratorio.
 */
export default function AlertasEquipos({
    equipos,
}: {
    equipos: EquipoPortal[];
}) {
    const [abierto, setAbierto] = useState(true);

    const alertas = equipos
        .filter(
            (
                equipo,
            ): equipo is EquipoPortal & { estado_vencimiento: EstadoAlerta } =>
                equipo.estado_vencimiento === 'VENCIDO' ||
                equipo.estado_vencimiento === 'PROXIMO_A_VENCER',
        )
        .sort(
            (a, b) =>
                Number(b.estado_vencimiento === 'VENCIDO') -
                    Number(a.estado_vencimiento === 'VENCIDO') ||
                (a.fecha_proximo_servicio ?? '').localeCompare(
                    b.fecha_proximo_servicio ?? '',
                ),
        );

    if (alertas.length === 0) {
        return (
            <div
                className="flex items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300"
                data-test="portal-alertas-vacio"
            >
                <CircleCheck className="h-4 w-4 text-emerald-600" />
                Todos tus equipos están al día
            </div>
        );
    }

    const vencidos = alertas.filter(
        (equipo) => equipo.estado_vencimiento === 'VENCIDO',
    ).length;
    const proximos = alertas.length - vencidos;

    return (
        <Collapsible
            open={abierto}
            onOpenChange={setAbierto}
            className="rounded-lg border border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/30"
            data-test="portal-alertas"
        >
            <CollapsibleTrigger asChild>
                <button
                    type="button"
                    className="flex w-full items-center justify-between gap-2 px-4 py-3 text-left"
                >
                    <span className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-medium">
                        <Siren className="h-4 w-4 text-red-600" />
                        Requieren tu atención ({alertas.length})
                        <span className="font-normal text-muted-foreground">
                            {[
                                vencidos > 0
                                    ? `${vencidos} ${vencidos === 1 ? 'vencido' : 'vencidos'}`
                                    : null,
                                proximos > 0
                                    ? `${proximos} ${proximos === 1 ? 'próximo' : 'próximos'} a vencer`
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </span>
                    </span>
                    <ChevronDown
                        className={cn(
                            'h-4 w-4 shrink-0 transition-transform',
                            abierto && 'rotate-180',
                        )}
                    />
                </button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <ul className="max-h-96 overflow-y-auto border-t border-red-200 dark:border-red-900">
                    {alertas.map((equipo) => {
                        const estilo = ESTILO[equipo.estado_vencimiento];

                        return (
                            <li
                                key={equipo.id}
                                data-test="portal-alerta"
                                className={cn(
                                    'flex flex-wrap items-center justify-between gap-3 border-l-4 px-4 py-3 text-sm',
                                    estilo.fila,
                                )}
                            >
                                <div className="min-w-0 space-y-0.5">
                                    <p className="font-medium">
                                        {equipo.codigo}{' '}
                                        <span className="font-normal text-muted-foreground">
                                            {[
                                                equipo.tipo_equipo,
                                                equipo.modelo,
                                                equipo.bahia,
                                            ]
                                                .filter(Boolean)
                                                .map((dato) => ` · ${dato}`)
                                                .join('')}
                                        </span>
                                    </p>
                                    {equipo.fecha_proximo_servicio ? (
                                        <p className="text-xs text-muted-foreground">
                                            <span className="font-medium text-foreground">
                                                {textoPlazo(
                                                    equipo.fecha_proximo_servicio,
                                                )}
                                            </span>
                                            {` · Servicio programado: ${formatearFecha(equipo.fecha_proximo_servicio)}`}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge className={estilo.badge}>
                                        {estilo.etiqueta}
                                    </Badge>
                                    <Button
                                        asChild
                                        size="sm"
                                        className={estilo.boton}
                                        data-test="portal-alerta-ver"
                                    >
                                        <Link href={show(equipo.id).url}>
                                            Ver
                                        </Link>
                                    </Button>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            </CollapsibleContent>
        </Collapsible>
    );
}
