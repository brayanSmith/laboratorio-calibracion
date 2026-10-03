import type { TipoMantenimientoProgramacion } from './equipos';

export type EstadoMantenimiento =
    | 'PENDIENTE'
    | 'EN_PROCESO'
    | 'FALTA_REPUESTOS'
    | 'FINALIZADO';

export type EstadoEquipoMantenimiento = 'OPERATIVO' | 'FUERA_DE_SERVICIO';

export type MantenimientoOption = {
    id: number;
    nombre: string;
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
    equipo: {
        codigo: string;
        modelo: string;
        tipo_equipo: { nombre: string };
        cliente: { nombre: string } | null;
    };
};

export type MantenimientoOptions = {
    tecnicos: MantenimientoOption[];
    novedadesMantenimiento: MantenimientoOption[];
};
