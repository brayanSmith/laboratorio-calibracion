import type { EstadoCalibracion, EstadoMantenimiento } from '@/types';

/**
 * Estado general de un mantenimiento o una calibración, para las tablas: solo si está
 * pendiente, en proceso o ya finalizado. Cómo quedó el equipo se muestra aparte, en
 * "Estado final del equipo".
 */
export type EstadoServicio = 'PENDIENTE' | 'EN_PROCESO' | 'FINALIZADO';

/** Pendiente en ámbar, en proceso en azul, finalizado en verde. */
export const estadosServicio: Record<
    EstadoServicio,
    { label: string; className: string }
> = {
    PENDIENTE: {
        label: 'Pendiente',
        className: '!border-transparent !bg-amber-500 !text-white',
    },
    EN_PROCESO: {
        label: 'En proceso',
        className: '!border-transparent !bg-blue-500 !text-white',
    },
    FINALIZADO: {
        label: 'Finalizado',
        className: '!border-transparent !bg-emerald-500 !text-white',
    },
};

export const opcionesEstadoServicio = Object.entries(estadosServicio).map(
    ([value, { label }]) => ({ value, label }),
);

/** Una calibración devuelta a mantenimiento o fuera de servicio también terminó. */
export function estadoServicioCalibracion(
    estado: EstadoCalibracion,
): EstadoServicio {
    if (estado === 'PENDIENTE' || estado === 'EN_PROCESO') {
        return estado;
    }

    return 'FINALIZADO';
}

/** Un mantenimiento al que le faltan repuestos sigue en proceso. */
export function estadoServicioMantenimiento(
    estado: EstadoMantenimiento,
): EstadoServicio {
    if (estado === 'FALTA_REPUESTOS') {
        return 'EN_PROCESO';
    }

    return estado;
}
