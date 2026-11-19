export type Bahia = {
    id: number;
    nombre: string;
    area_id: number;
    area_nombre: string;
    equipos_count: number;
    /** Usuarios asignados a la bahía. */
    usuario_ids: number[];
    /** Clientes asignados a la bahía. */
    cliente_ids: number[];
};

/** Cliente del tenant que se puede asignar a bahías. */
export type BahiaClienteOption = {
    id: number;
    nombre: string;
    numero_documento: string | null;
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
