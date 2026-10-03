import type { EstadoEquipoMantenimiento, EstadoMantenimiento } from '@/types';

export const estadosMantenimiento = [
    { value: 'PENDIENTE', label: 'Pendiente' },
    { value: 'EN_PROCESO', label: 'En proceso' },
    { value: 'FALTA_REPUESTOS', label: 'Falta repuestos' },
    { value: 'FINALIZADO', label: 'Finalizado' },
];

/** Pendiente en amarillo, en proceso en azul, falta repuestos en rojo, finalizado en verde. */
export const colorEstadoMantenimiento: Record<EstadoMantenimiento, string> = {
    PENDIENTE: '!border-transparent !bg-amber-500 !text-white',
    EN_PROCESO: '!border-transparent !bg-blue-500 !text-white',
    FALTA_REPUESTOS: '!border-transparent !bg-red-500 !text-white',
    FINALIZADO: '!border-transparent !bg-emerald-500 !text-white',
};

export const estadosEquipoMantenimiento = [
    { value: 'OPERATIVO', label: 'Operativo' },
    { value: 'FUERA_DE_SERVICIO', label: 'Fuera de servicio' },
];

/** Operativo en verde, fuera de servicio en rojo. */
export const colorEstadoEquipoMantenimiento: Record<
    EstadoEquipoMantenimiento,
    string
> = {
    OPERATIVO: '!border-transparent !bg-emerald-500 !text-white',
    FUERA_DE_SERVICIO: '!border-transparent !bg-red-500 !text-white',
};
