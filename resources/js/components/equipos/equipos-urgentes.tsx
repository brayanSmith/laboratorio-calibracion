import { ChevronDown, Siren } from 'lucide-react';
import { useState } from 'react';
import ViewEquipoModal from '@/components/equipos/view-equipo-modal';
import { estadosVencimiento } from '@/components/equipos/equipo-programacion-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { BADGE_VENCIMIENTO } from '@/lib/vencimiento-programacion';
import type { Equipo, EquipoFormOptions, EstadoVencimiento } from '@/types';

/** Equipo completo, con su estado de vencimiento más urgente y la fecha de ese servicio. */
export type EquipoUrgente = Equipo & {
    estado_vencimiento: EstadoVencimiento;
    fecha_proximo_servicio: string | null;
};

/** Fila coloreada como alerta: roja si está vencido, ámbar si está por vencer. */
const FILA: Record<EstadoVencimiento, string> = {
    VENCIDO: 'border-l-red-600 bg-red-100/70 dark:bg-red-950/40',
    PROXIMO_A_VENCER: 'border-l-amber-500 bg-amber-100/70 dark:bg-amber-950/40',
    AL_DIA: '',
};

const BOTON: Record<EstadoVencimiento, string> = {
    VENCIDO: 'bg-red-700 text-white hover:bg-red-800',
    PROXIMO_A_VENCER: 'bg-amber-600 text-white hover:bg-amber-700',
    AL_DIA: '',
};

type Props = {
    equipos: EquipoUrgente[];
    options: EquipoFormOptions;
};

/** Equipos vencidos (y, si sobra lugar, próximos a vencer) para atender de inmediato. */
export default function EquiposUrgentes({ equipos, options }: Props) {
    const [abierto, setAbierto] = useState(true);

    return (
        <Collapsible
            open={abierto}
            onOpenChange={setAbierto}
            className="rounded-lg border border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/30"
            data-test="equipos-urgentes"
        >
            <CollapsibleTrigger asChild>
                <button
                    type="button"
                    className="flex w-full items-center justify-between gap-2 px-4 py-3 text-left"
                >
                    <span className="flex items-center gap-2 text-sm font-medium">
                        <Siren className="h-4 w-4 text-red-600" />
                        Requieren atención ({equipos.length})
                    </span>
                    <ChevronDown
                        className={cn(
                            'h-4 w-4 transition-transform',
                            abierto && 'rotate-180',
                        )}
                    />
                </button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                {equipos.length === 0 ? (
                    <p className="border-t px-4 py-6 text-center text-sm text-muted-foreground">
                        No hay equipos vencidos ni próximos a vencer.
                    </p>
                ) : (
                    <ul className="border-t border-red-200 dark:border-red-900">
                        {equipos.map((equipo) => (
                            <li
                                key={equipo.id}
                                data-test="equipo-urgente"
                                className={cn(
                                    'flex flex-wrap items-center justify-between gap-3 border-l-4 px-4 py-3 text-sm',
                                    FILA[equipo.estado_vencimiento],
                                )}
                            >
                                <div className="min-w-0 space-y-0.5">
                                    <p className="font-medium">
                                        {equipo.codigo}{' '}
                                        <span className="font-normal text-muted-foreground">
                                            · {equipo.modelo}
                                            {equipo.tipo_equipo?.nombre
                                                ? ` · ${equipo.tipo_equipo.nombre}`
                                                : ''}
                                        </span>
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {equipo.cliente?.nombre ??
                                            'Sin cliente'}
                                        {equipo.fecha_proximo_servicio
                                            ? ` · Próximo servicio: ${equipo.fecha_proximo_servicio}`
                                            : ''}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        className={
                                            BADGE_VENCIMIENTO[
                                                equipo.estado_vencimiento
                                            ]
                                        }
                                    >
                                        {estadosVencimiento.find(
                                            (opcion) =>
                                                opcion.value ===
                                                equipo.estado_vencimiento,
                                        )?.label ?? equipo.estado_vencimiento}
                                    </Badge>
                                    <ViewEquipoModal
                                        equipo={equipo}
                                        options={options}
                                    >
                                        <Button
                                            type="button"
                                            className={
                                                BOTON[equipo.estado_vencimiento]
                                            }
                                            size="sm"
                                            data-test="equipo-urgente-ver"
                                        >
                                            Ver
                                        </Button>
                                    </ViewEquipoModal>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CollapsibleContent>
        </Collapsible>
    );
}
