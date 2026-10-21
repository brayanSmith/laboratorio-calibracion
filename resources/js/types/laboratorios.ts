export type Laboratorio = {
    id: number;
    nombre: string;
    descripcion: string | null;
    direccion: string | null;
    email: string | null;
    telefono: string | null;
    /** Solo uno por tenant: es el laboratorio de las calibraciones locales. */
    principal: boolean;
    calibraciones_count: number;
};
