import type {
    EquipoFichaTecnica,
    TipoMantenimientoProgramacion,
} from './equipos';

export type EstadoMantenimiento =
    | 'PENDIENTE'
    | 'EN_PROCESO'
    | 'FALTA_REPUESTOS'
    | 'FINALIZADO';

export type EstadoEquipoMantenimiento = 'OPERATIVO' | 'FUERA_DE_SERVICIO';
export type NivelRiesgo = 'ALTO' | 'MEDIO' | 'BAJO';

export type MantenimientoOption = {
    id: number;
    nombre: string;
};

export type MantenimientoItemOption = {
    id: number;
    codigo: string;
    nombre: string;
};

/** Item del checklist, copiado del tipo de equipo al agendar el mantenimiento. */
export type MantenimientoCheckListItem = {
    id: number;
    nombre: string;
    cumple: boolean;
    observacion: string | null;
};

export type MantenimientoDefecto = {
    id: number;
    defecto_identificado: string;
    nivel_riesgo: NivelRiesgo;
    accion_correctiva: string | null;
};

/** Un ítem (repuesto/insumo) usado durante el mantenimiento. */
export type MantenimientoItemUsado = {
    id: number;
    item_codigo: string;
    item_nombre: string;
    cantidad: string;
};

export type MantenimientoComentario = {
    id: number;
    comentario: string;
    autor: string;
    fecha: string;
};

export type MantenimientoFoto = {
    id: number;
    imagen_url: string;
    descripcion: string | null;
};

/**
 * Un mantenimiento, creado desde "Agendar Mantenimiento" (ver OrdenTrabajoController).
 * No se crea desde este módulo: solo se edita o elimina.
 */
export type Mantenimiento = {
    id: number;
    orden_trabajo_id: number;
    orden_trabajo_codigo: string;
    tipo_mantenimiento: TipoMantenimientoProgramacion;
    fecha_mantenimiento: string;
    descripcion: string | null;
    estado_inicial_equipo: EstadoEquipoMantenimiento | null;
    estado_final_equipo: EstadoEquipoMantenimiento | null;
    estado_mantenimiento: EstadoMantenimiento;
    tecnico_id: number;
    tecnico_nombre: string;
    firmado: boolean;
    novedad_id: number | null;
    novedad_nombre: string | null;
    /** Fecha/hora ISO en que se inició el tiempo_servicio en curso, o null si no hay uno. */
    tiempo_servicio_inicio: string | null;
    equipo: {
        codigo: string;
        modelo: string;
        numero_serie: string;
        tipo_tecnologia: string;
        ficha_tecnica: EquipoFichaTecnica | null;
        fabricante: { nombre: string };
        tipo_equipo: { nombre: string; tipo_mantenimiento: string };
        cliente: { nombre: string } | null;
    };
    checklist: MantenimientoCheckListItem[];
    defectos: MantenimientoDefecto[];
    items_usados: MantenimientoItemUsado[];
    comentarios: MantenimientoComentario[];
    galeria: MantenimientoFoto[];
};

export type MantenimientoOptions = {
    tecnicos: MantenimientoOption[];
    novedadesMantenimiento: MantenimientoOption[];
    items: MantenimientoItemOption[];
};
