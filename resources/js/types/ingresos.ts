import type { EquipoProgramacionBusquedaItem } from './equipos';

export type EstadoIngreso = 'PENDIENTE' | 'INGRESADO' | 'CANCELADO';

export type Ingreso = {
    id: number;
    bahia_id: number;
    bahia_nombre: string;
    desde: string;
    hasta: string;
    /** Nulo hasta que se edita el ingreso. */
    tecnico_recibe_id: number | null;
    tecnico_recibe_nombre: string | null;
    /** Nulo hasta que se edita el ingreso. */
    cliente_entrega_id: number | null;
    cliente_entrega_nombre: string | null;
    firma_url: string | null;
    estado_ingreso: EstadoIngreso;
    /** Notas generales cuando el ingreso queda aprobado. */
    novedad: string | null;
    /** Solo cuando estado_ingreso es CANCELADO. */
    motivo_cancelacion: string | null;
    /** Equipos (preventivos o correctivos) ya enlazados a este ingreso. */
    equipo_programaciones: EquipoProgramacionBusquedaItem[];
};

export type IngresoOption = {
    id: number;
    nombre: string;
};

export type IngresoOptions = {
    bahias: IngresoOption[];
    tecnicos: IngresoOption[];
    clientes: IngresoOption[];
};
