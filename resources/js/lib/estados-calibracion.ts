import type { EstadoCalibracion } from '@/types';

/** Pendiente en ámbar, en proceso en azul, finalizado en verde, devolver a mantenimiento
 * en amarillo (más claro que pendiente, con texto oscuro para que se lea) y fuera de
 * servicio en rojo. */
export const colorEstadoCalibracion: Record<EstadoCalibracion, string> = {
    PENDIENTE: '!border-transparent !bg-amber-500 !text-white',
    EN_PROCESO: '!border-transparent !bg-blue-500 !text-white',
    FINALIZADO: '!border-transparent !bg-emerald-500 !text-white',
    DEVOLVER_MANTENIMIENTO:
        '!border-transparent !bg-yellow-400 !text-yellow-950',
    FUERA_DE_SERVICIO: '!border-transparent !bg-red-500 !text-white',
};
