import type { EstadoIngreso } from '@/types';

export const estadosIngreso = [
    { value: 'PENDIENTE', label: 'Pendiente' },
    { value: 'INGRESADO', label: 'Aprobado' },
    { value: 'CANCELADO', label: 'Cancelado' },
];

/** Pendiente en amarillo, aprobado en verde, cancelado en rojo. */
export const colorEstadoIngreso: Record<EstadoIngreso, string> = {
    PENDIENTE: '!border-transparent !bg-amber-500 !text-white',
    INGRESADO: '!border-transparent !bg-emerald-500 !text-white',
    CANCELADO: '!border-transparent !bg-red-500 !text-white',
};
