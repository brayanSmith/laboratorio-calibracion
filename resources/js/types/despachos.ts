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
    fecha_programada: string | null;
    /** Día en que realmente se entregó; se fija sola cuando la entrega queda recibida. */
    fecha_despacho: string | null;
    entrega_autorizada: boolean;
    cliente_recibe_id: number | null;
    cliente_recibe_nombre: string | null;
    firma_url: string | null;
    entrega_recibida: boolean;
    estado_despacho: 'PENDIENTE' | 'AUTORIZADO' | 'ENTREGADO';
    novedad_id: number | null;
    novedad_nombre: string | null;
    /** Cambios de la fecha programada, del más reciente al más antiguo. */
    reagendamientos: DespachoReagendamiento[];
    equipo: {
        codigo: string;
        modelo: string;
        numero_serie: string;
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

/** Una vez en que cambió la fecha programada del despacho, con su motivo. */
export type DespachoReagendamiento = {
    id: number;
    fecha_anterior: string;
    fecha_nueva: string;
    motivo: string;
    /** Nulo si no hay usuario (ej. un cambio automático). */
    usuario: string | null;
    /** Cuándo se re-agendó (ISO). */
    fecha: string;
};
