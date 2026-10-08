export type CategoriaNovedad =
    | 'INGRESO'
    | 'MANTENIMIENTO'
    | 'CALIBRACION'
    | 'SALIDA';

export type Novedad = {
    id: number;
    nombre: string;
    categoria: CategoriaNovedad;
    usos_count: number;
};
