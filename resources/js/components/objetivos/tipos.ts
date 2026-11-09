export type TipoObjetivoUsuario = 'MANTENIMIENTO' | 'CALIBRACION';

/** Objetivos de un tipo en el año: por mes (1 a 12; sin los meses vacíos) y su total. */
export type ObjetivosDelTipo = {
    meses: Partial<Record<number, number>>;
    total: number;
};

/**
 * Objetivos de un usuario en un año: cuántos mantenimientos y cuántas calibraciones debe
 * hacer cada mes.
 */
export type ObjetivoUsuario = {
    clave: string;
    user_id: number;
    usuario: string | null;
    anio: number;
    tipos: Record<TipoObjetivoUsuario, ObjetivosDelTipo | null>;
};

export type Opcion = { id: number; nombre: string };

export const TIPOS_OBJETIVO_USUARIO: {
    value: TipoObjetivoUsuario;
    label: string;
}[] = [
    { value: 'MANTENIMIENTO', label: 'Mantenimientos' },
    { value: 'CALIBRACION', label: 'Calibraciones' },
];

export const MESES = [
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre',
] as const;
