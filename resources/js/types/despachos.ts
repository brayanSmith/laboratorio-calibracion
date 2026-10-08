/**
 * Un despacho, creado al finalizar una calibración (ver
 * CalibracionController::finalizar()). No se crea desde este módulo: solo se edita o
 * elimina. tecnico_entrega/cliente_recibe/firma/novedad quedan nulos hasta que se
 * agenda y se realiza la entrega (ver OrdenTrabajoController::storeDespacho()).
 */
export type Despacho = {
    id: number;
    orden_trabajo_id: number;
    orden_trabajo_codigo: string;
    tecnico_entrega_id: number | null;
    tecnico_entrega_nombre: string | null;
    entrega_autorizada: boolean;
    cliente_recibe_id: number | null;
    cliente_recibe_nombre: string | null;
    firma_url: string | null;
    entrega_recibida: boolean;
    novedad_id: number | null;
    novedad_nombre: string | null;
    equipo: {
        codigo: string;
        modelo: string;
        tipo_equipo: { nombre: string };
        cliente: { nombre: string };
    };
};

export type DespachoOption = {
    id: number;
    nombre: string;
};

export type DespachoOptions = {
    tecnicos: DespachoOption[];
    clientes: DespachoOption[];
    /** Catálogo de Novedad (categoría SALIDA), para la novedad del despacho. */
    novedadesDespacho: DespachoOption[];
};
