export type EstadoCalibracion =
    | 'PENDIENTE'
    | 'EN_PROCESO'
    | 'FINALIZADO'
    | 'DEVOLVER_MANTENIMIENTO';

export type CalibracionOption = {
    id: number;
    nombre: string;
};

/**
 * Una calibración, creada desde "Agendar Calibraciones" (ver OrdenTrabajoController).
 * No se crea desde este módulo: solo se edita o elimina. laboratorio/solicitante/
 * procedimiento/novedad quedan nulos hasta que se realiza la calibración.
 */
export type Calibracion = {
    id: number;
    orden_trabajo_id: number;
    orden_trabajo_codigo: string;
    laboratorio_id: number | null;
    laboratorio_nombre: string | null;
    solicitante_id: number | null;
    solicitante_nombre: string | null;
    tecnico_id: number;
    tecnico_nombre: string;
    temperatura: string | null;
    humedad: string | null;
    procedimiento_id: number | null;
    procedimiento_nombre: string | null;
    ajustes_requeridos: boolean;
    estado_calibracion: EstadoCalibracion;
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

export type CalibracionOptions = {
    tecnicos: CalibracionOption[];
    laboratorios: CalibracionOption[];
    areas: CalibracionOption[];
    procedimientos: CalibracionOption[];
    novedadesCalibracion: CalibracionOption[];
};
