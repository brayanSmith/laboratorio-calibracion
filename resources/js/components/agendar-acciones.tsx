import { usePage, useHttp } from '@inertiajs/react';
import { Gauge, Truck, Wrench } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { opcionesAgendar } from '@/actions/App/Http/Controllers/OrdenTrabajoController';
import AgendarCalibracionesModal from '@/components/ordenes-trabajo/agendar-calibraciones-modal';
import AgendarDespachosModal from '@/components/ordenes-trabajo/agendar-despachos-modal';
import AgendarMantenimientoModal from '@/components/ordenes-trabajo/agendar-mantenimiento-modal';
import { Button } from '@/components/ui/button';
import type { IngresoOption } from '@/types';

type Opciones = {
    tecnicos: IngresoOption[];
    empresasTerceras: IngresoOption[];
};

type Atajo = 'mantenimiento' | 'calibracion' | 'despachos';

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

function BotonAtajo({
    icono: Icono,
    titulo,
    cantidad,
    dataTest,
    onClick,
}: {
    icono: LucideIcon;
    titulo: string;
    cantidad: number;
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
                onClick={onClick}
                data-test={dataTest}
                className="group/atajo h-9 gap-0 overflow-hidden px-2.5"
            >
                <Icono className="size-5 shrink-0" />
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

/**
 * Atajos del menú superior para agendar mantenimientos, calibraciones y despachos desde
 * cualquier página, con el número de equipos listos para cada uno como globito, al estilo
 * de las notificaciones. Cada uno abre la misma modal que antes vivía en Ingresos.
 */
export function AgendarAcciones() {
    const { porAgendar } = usePage().props;
    const [abierto, setAbierto] = useState<Atajo | null>(null);
    const { get, response: opciones } = useHttp<
        Record<string, never>,
        Opciones
    >();

    if (!porAgendar) {
        return null;
    }

    // Los técnicos y las empresas terceras solo se piden la primera vez que se abre una modal.
    const abrir = (atajo: Atajo) => {
        setAbierto(atajo);

        if (!opciones) {
            void get(opcionesAgendar().url);
        }
    };

    const alCambiar = (atajo: Atajo) => (open: boolean) =>
        setAbierto(open ? atajo : null);

    const tecnicos = opciones?.tecnicos ?? [];
    const empresasTerceras = opciones?.empresasTerceras ?? [];

    return (
        <div
            className="ml-auto flex items-center gap-1"
            data-test="agendar-acciones"
        >
            <BotonAtajo
                icono={Wrench}
                titulo="Agendar mantenimiento"
                cantidad={porAgendar.mantenimiento}
                dataTest="agendar-mantenimiento-button"
                onClick={() => abrir('mantenimiento')}
            />
            <BotonAtajo
                icono={Gauge}
                titulo="Agendar calibraciones"
                cantidad={porAgendar.calibracion}
                dataTest="agendar-calibraciones-button"
                onClick={() => abrir('calibracion')}
            />
            <BotonAtajo
                icono={Truck}
                titulo="Agendar despachos"
                cantidad={porAgendar.despachos}
                dataTest="agendar-despachos-button"
                onClick={() => abrir('despachos')}
            />

            <AgendarMantenimientoModal
                tecnicos={tecnicos}
                empresasTerceras={empresasTerceras}
                open={abierto === 'mantenimiento'}
                onOpenChange={alCambiar('mantenimiento')}
            />
            <AgendarCalibracionesModal
                tecnicos={tecnicos}
                empresasTerceras={empresasTerceras}
                open={abierto === 'calibracion'}
                onOpenChange={alCambiar('calibracion')}
            />
            <AgendarDespachosModal
                tecnicos={tecnicos}
                open={abierto === 'despachos'}
                onOpenChange={alCambiar('despachos')}
            />
        </div>
    );
}
