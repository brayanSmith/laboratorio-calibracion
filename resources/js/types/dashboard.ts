/**
 * Qué indicadores ve el usuario (ver DashboardController). Las opciones de sus filtros
 * llegan con los datos de cada indicador, como listas dependientes.
 */
export type IndicadoresDashboard = {
    permisos: {
        tiempos_atencion: boolean;
        desempeno_tecnicos: boolean;
        cumplimiento: boolean;
    };
};
export type TiemposAtencion = {
    resumen: {
        /** Despachos con la entrega recibida en el rango. */
        cantidad: number;
        /** Cuántos tienen fecha de recepción en el ingreso (los que entran en el promedio). */
        con_tiempo: number;
        promedio_dias: number | null;
        minimo_dias: number | null;
        maximo_dias: number | null;
    };
    equipos: {
        despacho_id: number;
        orden_trabajo: string;
        equipo: string;
        modelo: string;
        tipo_equipo: string;
        cliente: string | null;
        laboratorio: string;
        fecha_recepcion: string | null;
        fecha_despacho: string;
        dias: number | null;
    }[];
    /**
     * Opciones de los filtros como listas dependientes (área → laboratorio → magnitud → tipo
     * de equipo): cada una con lo que dejan los filtros anteriores.
     */
    opciones: {
        areas: OpcionFiltroDependiente[];
        /** El laboratorio propio llega con el id "propio". */
        laboratorios: OpcionFiltroDependiente[];
        magnitudes: OpcionFiltroDependiente[];
        tipos_equipo: OpcionFiltroDependiente[];
    };
    mensual: {
        mes: string;
        cantidad: number;
        promedio_dias: number | null;
    }[];
    objetivos: ObjetivosDelPeriodo;
};

export type OpcionFiltroDependiente = {
    id: number | string;
    nombre: string;
};

export type ResumenServicios = {
    cantidad: number;
    /** Nulo si ningún servicio tiene tiempo registrado. */
    promedio_horas: number | null;
};

export type DesempenoTecnicos = {
    responsables: {
        tipo: 'TECNICO' | 'TERCERO';
        responsable_id: number;
        responsable: string;
        mantenimientos: ResumenServicios;
        calibraciones: ResumenServicios;
    }[];
    totales: {
        mantenimientos: ResumenServicios;
        calibraciones: ResumenServicios;
    };
    mensual: {
        mes: string;
        mantenimientos: ResumenServicios;
        calibraciones: ResumenServicios;
    }[];
    objetivos: ObjetivosDelPeriodo;
    /** Listas dependientes: laboratorio → técnico → magnitud → tipo de equipo. */
    opciones: {
        /** El laboratorio propio llega con el id "propio". */
        laboratorios: OpcionFiltroDependiente[];
        tecnicos: OpcionFiltroDependiente[];
        magnitudes: OpcionFiltroDependiente[];
        tipos_equipo: OpcionFiltroDependiente[];
    };
};

export type Cumplimiento = {
    resumen: {
        programados: number;
        atendidos: number;
        pendientes: number;
        porcentaje: number | null;
    };
    por_area: {
        area: string;
        programados: number;
        atendidos: number;
        porcentaje: number | null;
    }[];
    pendientes: {
        equipo_id: number;
        equipo: string;
        modelo: string;
        tipo_equipo: string | null;
        area: string | null;
        fecha_programada: string;
        estado_programacion: string;
    }[];
    mensual: {
        mes: string;
        programados: number;
        atendidos: number;
        porcentaje: number | null;
    }[];
    objetivos: ObjetivosDelPeriodo;
    /** Listas dependientes: área → magnitud → tipo de equipo. */
    opciones: {
        areas: OpcionFiltroDependiente[];
        magnitudes: OpcionFiltroDependiente[];
        tipos_equipo: OpcionFiltroDependiente[];
    };
};

/** Módulos del dashboard con objetivos anuales (ver ModuloObjetivo). */
export type ModuloObjetivo =
    'TIEMPOS_ATENCION' | 'DESEMPENO_TECNICOS' | 'CUMPLIMIENTO';

/** Cada objetivo anual que se puede definir (ver ConceptoObjetivo). */
export type ConceptoObjetivo =
    | 'TIEMPO_ATENCION_DIAS'
    | 'CANTIDAD_MANTENIMIENTOS'
    | 'PROMEDIO_HORAS_MANTENIMIENTO'
    | 'CANTIDAD_CALIBRACIONES'
    | 'PROMEDIO_HORAS_CALIBRACION'
    | 'CUMPLIMIENTO_PORCENTAJE';

/** Objetivos de cada año del periodo de un indicador (uno por año, aunque esté vacío). */
export type ObjetivosDelPeriodo = {
    anio: number;
    valores: Partial<Record<ConceptoObjetivo, number>>;
}[];

/** Objetivos de un módulo para el formulario (ver ObjetivosDashboardController). */
export type ObjetivosModulo = {
    conceptos: {
        concepto: ConceptoObjetivo;
        label: string;
        unidad: string;
        /** Cómo se escribe y se muestra: las horas, con horas y minutos. */
        formato: 'horas' | 'dias' | 'cantidad' | 'porcentaje';
        maximo: number | null;
    }[];
    anios: ObjetivosDelPeriodo;
};
