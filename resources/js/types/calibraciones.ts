import type { EquipoFichaTecnica } from './equipos';

export type EstadoCalibracion =
    | 'PENDIENTE'
    | 'EN_PROCESO'
    | 'FINALIZADO'
    | 'DEVOLVER_MANTENIMIENTO';

export type CalibracionOption = {
    id: number;
    nombre: string;
};

export type CalibracionAreaOption = {
    id: number;
    nombre: string;
    direccion: string | null;
    descripcion: string | null;
};

/**
 * Una fila de resultados de la calibración, creada al agendar según el
 * detalle_medicion_alcance del equipo (ver
 * OrdenTrabajoController::crearDetallesMedicionCalibracion()). valor_referencia,
 * unidad_simbolo, emp e incertidumbre son datos de referencia conocidos de antemano; el
 * resto se completa cuando se realiza la medición real.
 */
export type CalibracionDetalleMedicion = {
    id: number;
    valor_referencia: string;
    unidad_simbolo: string;
    valor_instrumento: string | null;
    error_encontrado: string | null;
    emp: string;
    incertidumbre: string;
    error_porcentaje: string | null;
    emp_porcentaje: string | null;
    emp_porcentaje_positivo: string | null;
    emp_porcentaje_negativo: string | null;
    /** APROBADO si error_encontrado <= emp, NO_APROBADO si no. Calculado en el servidor. */
    resultado_calibracion: 'APROBADO' | 'NO_APROBADO' | null;
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
    fecha_calibracion: string;
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
    /** ISO string de cuándo se inició el tiempo_servicio en curso, o null si no hay uno. */
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
    detalles_medicion: CalibracionDetalleMedicion[];
};

export type CalibracionOptions = {
    tecnicos: CalibracionOption[];
    laboratorios: CalibracionOption[];
    areas: CalibracionAreaOption[];
    procedimientos: CalibracionOption[];
    novedadesCalibracion: CalibracionOption[];
};
