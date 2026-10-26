export type CategoriaNovedad =
    | 'INGRESO'
    | 'MANTENIMIENTO'
    | 'CALIBRACION'
    | 'SALIDA';

export type Novedad = {
    id: number;
    nombre: string;
    categoria: CategoriaNovedad;
    /** Si al reportarla el equipo queda inactivo. */
    inactivar_equipo: boolean;
    usos_count: number;
};
