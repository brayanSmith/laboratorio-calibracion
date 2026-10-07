import type { EstadoCalibracion } from '@/types';

export const estadosCalibracion = [
    { value: 'PENDIENTE', label: 'Pendiente' },
    { value: 'EN_PROCESO', label: 'En proceso' },
    { value: 'FINALIZADO', label: 'Finalizado' },
    { value: 'DEVOLVER_MANTENIMIENTO', label: 'Devolver a mantenimiento' },
];

/** Pendiente en amarillo, en proceso en azul, finalizado en verde, devolver a
 * mantenimiento en rojo. */
export const colorEstadoCalibracion: Record<EstadoCalibracion, string> = {
    PENDIENTE: '!border-transparent !bg-amber-500 !text-white',
    EN_PROCESO: '!border-transparent !bg-blue-500 !text-white',
    FINALIZADO: '!border-transparent !bg-emerald-500 !text-white',
    DEVOLVER_MANTENIMIENTO: '!border-transparent !bg-red-500 !text-white',
};
