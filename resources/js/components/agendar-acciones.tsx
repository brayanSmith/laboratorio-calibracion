import { usePage, useHttp } from '@inertiajs/react';
import { CalendarDays, Gauge, Truck, Wrench } from 'lucide-react';
import { useState } from 'react';
import { useDesplazamientoHorizontal } from '@/hooks/use-desplazamiento-horizontal';
import { opcionesAgendar } from '@/actions/App/Modules/OrdenesTrabajo/Http/Controllers/OrdenTrabajoController';
import AgendaModal from '@/components/agenda/agenda-modal';
import AccesosModulos, {
    useModulosOperacion,
} from '@/components/accesos-modulos';
import BotonAtajo from '@/components/boton-atajo';
import ImportarDatos from '@/components/importaciones/importar-datos';
import AgendarCalibracionesModal from '@/components/ordenes-trabajo/agendar-calibraciones-modal';
import AgendarDespachosModal from '@/components/ordenes-trabajo/agendar-despachos-modal';
import AgendarMantenimientoModal from '@/components/ordenes-trabajo/agendar-mantenimiento-modal';
import { usePermissions } from '@/hooks/use-permissions';
import { permisoImportacion, tiposImportacion } from '@/lib/tipos-importacion';
import { cn } from '@/lib/utils';
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
    const desplazamiento = useDesplazamientoHorizontal<HTMLElement>();
    const { get, response: opciones } = useHttp<
        Record<string, never>,
        Opciones
    >();

    const puedeVerAgenda = can('agenda.ver');
    const puedeImportar = tiposImportacion.some((tipo) =>
        can(permisoImportacion(tipo.value)),
    );
    const contadores = porAgendar ?? null;
    const modulos = useModulosOperacion();
    const hayAtajos = contadores !== null || puedeVerAgenda || puedeImportar;

    if (!hayAtajos && modulos.length === 0) {
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
        // En móvil, barra fija en la parte inferior (como las apps), con los módulos de la
        // operación y los atajos, que se desliza de lado si no caben; en escritorio, solo los
        // atajos a la derecha del menú superior.
        <div
            className="fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-30 md:static md:z-auto md:ml-auto"
            data-test="agendar-acciones"
        >
            {/* El fondo de la barra va aparte: el contenido se desliza (overflow) y necesita
                espacio arriba, dentro de él, para el botón activo que sobresale. */}
            <div
                aria-hidden="true"
                className={cn(
                    'absolute inset-x-0 bottom-0 rounded-3xl border bg-background shadow-[0_10px_40px_-10px_rgb(0_0_0/0.35)] md:hidden',
                    desplazamiento.desborda ? 'h-[5.25rem]' : 'h-[4.5rem]',
                )}
            />
            <nav
                ref={desplazamiento.ref}
                onScroll={desplazamiento.onScroll}
                aria-label="Accesos rápidos"
                className={cn(
                    'relative flex snap-x [scrollbar-width:none] items-end gap-0.5 overflow-x-auto overscroll-x-contain rounded-b-3xl px-1.5 pt-7 md:snap-none md:items-center md:gap-1 md:overflow-visible md:rounded-none md:p-0 [&::-webkit-scrollbar]:hidden',
                    desplazamiento.desborda ? 'pb-4' : 'pb-1.5',
                )}
            >
                <AccesosModulos modulos={modulos} />
                {modulos.length > 0 && hayAtajos ? (
                    <span
                        aria-hidden="true"
                        className="mx-1 mb-4 h-8 w-px shrink-0 self-end bg-border md:hidden"
                    />
                ) : null}
                {puedeImportar ? <ImportarDatos /> : null}
                {puedeVerAgenda ? (
                    <BotonAtajo
                        icono={CalendarDays}
                        titulo="Agenda"
                        etiqueta="Agenda"
                        activo={abierto === 'agenda'}
                        cantidad={0}
                        dataTest="agenda-button"
                        onClick={() => setAbierto('agenda')}
                    />
                ) : null}
                {contadores?.mantenimiento != null ? (
                    <BotonAtajo
                        icono={Wrench}
                        titulo="Agendar mantenimiento"
                        etiqueta="Agendar mant."
                        activo={abierto === 'mantenimiento'}
                        cantidad={contadores.mantenimiento}
                        dataTest="agendar-mantenimiento-button"
                        onClick={() => abrir('mantenimiento')}
                    />
                ) : null}
                {contadores?.calibracion != null ? (
                    <BotonAtajo
                        icono={Gauge}
                        titulo="Agendar calibraciones"
                        etiqueta="Agendar calib."
                        activo={abierto === 'calibracion'}
                        cantidad={contadores.calibracion}
                        dataTest="agendar-calibraciones-button"
                        onClick={() => abrir('calibracion')}
                    />
                ) : null}
                {contadores?.despachos != null ? (
                    <BotonAtajo
                        icono={Truck}
                        titulo="Agendar despachos"
                        etiqueta="Agendar desp."
                        activo={abierto === 'despachos'}
                        cantidad={contadores.despachos}
                        dataTest="agendar-despachos-button"
                        onClick={() => abrir('despachos')}
                    />
                ) : null}
            </nav>
            {/* Indicador de desplazamiento: el navegador del móvil oculta la barra de scroll,
                así que se dibuja una propia para que se note que hay más botones. */}
            {desplazamiento.desborda ? (
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute bottom-2 left-1/2 h-1 w-28 -translate-x-1/2 overflow-hidden rounded-full bg-muted md:hidden"
                    data-test="accesos-indicador-scroll"
                >
                    <div
                        className="absolute inset-y-0 rounded-full bg-foreground/40"
                        style={{
                            width: `${desplazamiento.visible * 100}%`,
                            left: `${desplazamiento.avance * (1 - desplazamiento.visible) * 100}%`,
                        }}
                    />
                </div>
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
