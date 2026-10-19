import { usePage, useHttp } from '@inertiajs/react';
import { CalendarDays, Gauge, Truck, Wrench } from 'lucide-react';
import { useState } from 'react';
import { opcionesAgendar } from '@/actions/App/Modules/OrdenesTrabajo/Http/Controllers/OrdenTrabajoController';
import AgendaModal from '@/components/agenda/agenda-modal';
import BotonAtajo from '@/components/boton-atajo';
import ImportarEquipos from '@/components/importaciones/importar-equipos';
import AgendarCalibracionesModal from '@/components/ordenes-trabajo/agendar-calibraciones-modal';
import AgendarDespachosModal from '@/components/ordenes-trabajo/agendar-despachos-modal';
import AgendarMantenimientoModal from '@/components/ordenes-trabajo/agendar-mantenimiento-modal';
import { usePermissions } from '@/hooks/use-permissions';
import type { IngresoOption } from '@/types';

type Opciones = {
    tecnicos: IngresoOption[];
    empresasTerceras: IngresoOption[];
};

type Atajo = 'mantenimiento' | 'calibracion' | 'despachos' | 'agenda';

/**
 * Atajos del menú superior para agendar mantenimientos, calibraciones y despachos desde
 * cualquier página, con el número de equipos listos para cada uno como globito, al estilo
 * de las notificaciones. Cada uno abre la misma modal que antes vivía en Ingresos.
 */
export function AgendarAcciones() {
    const { porAgendar } = usePage().props;
    const { can } = usePermissions();
    const [abierto, setAbierto] = useState<Atajo | null>(null);
    const { get, response: opciones } = useHttp<
        Record<string, never>,
        Opciones
    >();

    const puedeVerAgenda = can('agenda.ver');
    const puedeImportar = can('equipos.crear');
    const contadores = porAgendar ?? null;

    if (!contadores && !puedeVerAgenda && !puedeImportar) {
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
            {puedeImportar ? <ImportarEquipos /> : null}
            {puedeVerAgenda ? (
                <BotonAtajo
                    icono={CalendarDays}
                    titulo="Agenda"
                    cantidad={0}
                    dataTest="agenda-button"
                    onClick={() => setAbierto('agenda')}
                />
            ) : null}
            {contadores?.mantenimiento != null ? (
                <BotonAtajo
                    icono={Wrench}
                    titulo="Agendar mantenimiento"
                    cantidad={contadores.mantenimiento}
                    dataTest="agendar-mantenimiento-button"
                    onClick={() => abrir('mantenimiento')}
                />
            ) : null}
            {contadores?.calibracion != null ? (
                <BotonAtajo
                    icono={Gauge}
                    titulo="Agendar calibraciones"
                    cantidad={contadores.calibracion}
                    dataTest="agendar-calibraciones-button"
                    onClick={() => abrir('calibracion')}
                />
            ) : null}
            {contadores?.despachos != null ? (
                <BotonAtajo
                    icono={Truck}
                    titulo="Agendar despachos"
                    cantidad={contadores.despachos}
                    dataTest="agendar-despachos-button"
                    onClick={() => abrir('despachos')}
                />
            ) : null}
            <AgendaModal
                open={abierto === 'agenda'}
                onOpenChange={alCambiar('agenda')}
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
