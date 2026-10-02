import type { EstadoIngreso } from '@/types';

export const estadosIngreso = [
    { value: 'PENDIENTE', label: 'Pendiente' },
    { value: 'RECIBIDO', label: 'Recibido' },
    { value: 'CANCELADO', label: 'Cancelado' },
];

/** Pendiente en amarillo, recibido en verde, cancelado en rojo. */
export const colorEstadoIngreso: Record<EstadoIngreso, string> = {
    PENDIENTE: '!border-transparent !bg-amber-500 !text-white',
    RECIBIDO: '!border-transparent !bg-emerald-500 !text-white',
    CANCELADO: '!border-transparent !bg-red-500 !text-white',
};
