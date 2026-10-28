export type Bahia = {
    id: number;
    nombre: string;
    area_id: number;
    area_nombre: string;
    equipos_count: number;
    /** Usuarios asignados a la bahía. */
    usuario_ids: number[];
};

/** Usuario del tenant que se puede asignar a bahías. */
export type BahiaUsuarioOption = {
    id: number;
    name: string;
    email: string;
};

export type BahiaAreaOption = {
    id: number;
    nombre: string;
    /** "CRC - Centro de Reparación de Componentes". */
    etiqueta: string;
};
