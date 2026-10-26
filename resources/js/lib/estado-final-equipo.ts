import type { Calibracion, ServicioTerceroListado } from '@/types';

/** Verde si el equipo quedó bien, rojo si no, amarillo si vuelve a mantenimiento. */
export const BADGE_EQUIPO_OK =
    '!border-transparent !bg-emerald-500 !text-white';
export const BADGE_EQUIPO_MAL = '!border-transparent !bg-red-500 !text-white';
export const BADGE_EQUIPO_DEVUELTO =
    '!border-transparent !bg-yellow-400 !text-yellow-950';

/** Activo en verde, inactivo en rojo, para distinguirlos de un vistazo en la tabla de equipos. */
export const BADGE_EQUIPO_ACTIVO = BADGE_EQUIPO_OK;
export const BADGE_EQUIPO_INACTIVO = BADGE_EQUIPO_MAL;

export type EstadoFinalEquipoCalibracion =
    | 'OPERATIVO'
    | 'DEVUELTO_MANTENIMIENTO'
    | 'FUERA_DE_SERVICIO';

export const estadosFinalesEquipoCalibracion: Record<
    EstadoFinalEquipoCalibracion,
    { label: string; className: string }
> = {
    OPERATIVO: { label: 'Operativo', className: BADGE_EQUIPO_OK },
    DEVUELTO_MANTENIMIENTO: {
        label: 'Devuelto a mantenimiento',
        className: BADGE_EQUIPO_DEVUELTO,
    },
    FUERA_DE_SERVICIO: {
        label: 'Fuera de servicio',
        className: BADGE_EQUIPO_MAL,
    },
};

/**
 * Cómo quedó el equipo según cómo terminó la calibración local; nulo mientras no termina.
 */
export function estadoFinalEquipoCalibracion(
    calibracion: Pick<Calibracion, 'estado_calibracion'>,
): EstadoFinalEquipoCalibracion | null {
    switch (calibracion.estado_calibracion) {
        case 'FINALIZADO':
            return 'OPERATIVO';
        case 'DEVOLVER_MANTENIMIENTO':
            return 'DEVUELTO_MANTENIMIENTO';
        case 'FUERA_DE_SERVICIO':
            return 'FUERA_DE_SERVICIO';
        default:
            return null;
    }
}

export const estadosFinalesEquipoTercero: Record<
    NonNullable<ServicioTerceroListado['estado_final_equipo']>,
    { label: string; className: string }
> = {
    APROBADO: { label: 'Aprobado', className: BADGE_EQUIPO_OK },
    RECHAZADO: { label: 'Rechazado', className: BADGE_EQUIPO_MAL },
};
