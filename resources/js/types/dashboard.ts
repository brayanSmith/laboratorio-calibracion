import type { ObjetivoMensual } from '@/lib/periodos-grafico';
import type { CriticidadEquipo } from './equipos';

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
        numero_serie: string | null;
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
    /** El periodo justo antes del filtrado (mismo largo y filtros), para comparar. */
    periodo_anterior: {
        desde: string;
        hasta: string;
        totales: {
            mantenimientos: ResumenServicios;
            calibraciones: ResumenServicios;
        };
    };
    mensual: {
        mes: string;
        mantenimientos: ResumenServicios;
        calibraciones: ResumenServicios;
    }[];
    /** Tiempos objetivo por mes y tipo de equipo (solo de los tipos filtrados; sin filtro, de todos). */
    objetivos: ObjetivoMensual[];
    /**
     * Cantidad objetivo de cada mes de cada técnico filtrado (sin filtro, de todos los
     * usuarios); ver Objetivos por usuario.
     */
    objetivos_usuarios: {
        user_id: number;
        anio: number;
        mes: number;
        tipo: 'MANTENIMIENTO' | 'CALIBRACION';
        valor: number;
    }[];
    /** Cada servicio con su día y sus horas (null sin tiempo registrado), para los gráficos. */
    servicios: {
        fecha: string;
        tipo_servicio: 'MANTENIMIENTO' | 'CALIBRACION';
        responsable_tipo: 'TECNICO' | 'TERCERO';
        responsable_id: number;
        tipo_equipo_id: number | null;
        /** Códigos y nombres para ver los registros de cada responsable en el detalle. */
        orden_trabajo: string | null;
        equipo: string | null;
        modelo: string | null;
        numero_serie: string | null;
        tipo_equipo: string | null;
        magnitud: string | null;
        horas: number | null;
    }[];
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
        /** De los atendidos, los que quedaron fuera de servicio. */
        fuera_de_servicio: number;
        /** En qué servicio quedaron fuera de servicio (cada equipo una vez). */
        fuera_de_servicio_por_servicio: Record<
            'MANTENIMIENTO' | 'CALIBRACION',
            number
        >;
        pendientes: number;
        porcentaje: number | null;
        /** Por el servicio en que va cada equipo (cuenta una sola vez: suman los totales). */
        por_servicio: Record<
            'MANTENIMIENTO' | 'CALIBRACION',
            { atendidos: number; pendientes: number }
        >;
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
        numero_serie: string | null;
        tipo_equipo: string | null;
        area: string | null;
        bahia: string | null;
        criticidad: CriticidadEquipo | null;
        fecha_programada: string;
        estado_programacion: string;
    }[];
    /** Equipos que no llegaron al ingreso (cancelados o re-agendados), una fila por equipo y cadena de re-agendamientos. */
    no_recibidos: {
        programacion_id: number;
        ingreso_id: number;
        equipo_id: number;
        equipo: string;
        modelo: string;
        numero_serie: string | null;
        tipo_equipo: string | null;
        area: string | null;
        bahia: string | null;
        criticidad: CriticidadEquipo | null;
        fecha_programada: string | null;
        estado_programacion: string;
        novedad: string | null;
        observacion: string | null;
        fecha_re_agendamiento: string | null;
        /** Cuántas veces no llegó a lo largo de su cadena de re-agendamientos (la fila las junta). */
        veces_no_recibido: number;
    }[];
    /** Cuántos no recibidos hubo por cada novedad, de la más a la menos usada. */
    novedades_no_recibidos: {
        novedad: string;
        cantidad: number;
        porcentaje: number | null;
    }[];
    mensual: {
        mes: string;
        programados: number;
        atendidos: number;
        porcentaje: number | null;
    }[];
    /** Cada equipo programado con su fecha y si se atendió, para el gráfico combinado. */
    por_fecha: { fecha: string; atendido: boolean }[];
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
    | 'PROMEDIO_HORAS_MANTENIMIENTO'
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

/** Objetivos por mes y tipo de equipo de un módulo (ver ObjetivosMensualesDashboardController). */
export type ObjetivosMensualesModulo = {
    conceptos: ObjetivosModulo['conceptos'];
    tipos_equipo: { id: number; nombre: string }[];
    registros: {
        anio: number;
        tipo_equipo_id: number;
        tipo_equipo: string;
        /** Por número de mes (1 a 12), los valores de cada concepto. */
        meses: Partial<
            Record<string, Partial<Record<ConceptoObjetivo, number>>>
        >;
    }[];
};

/** Una vez en que cambió el estado de la programación, con quién y cuándo. */
export type SeguimientoTraza = {
    id: number;
    fase: string;
    seguimiento: string | null;
    observaciones: string | null;
    usuario: string | null;
    /** Fecha y hora ISO 8601. */
    fecha: string | null;
};

/** Mantenimiento, calibración (propio o de tercero) o despacho de una orden de trabajo. */
export type ServicioTraza = {
    servicio: 'MANTENIMIENTO' | 'CALIBRACION' | 'DESPACHO';
    estado: string;
    fecha: string | null;
    tercero: boolean;
};

export type IngresoTraza = {
    id: number;
    bahia: string | null;
    desde: string;
    hasta: string;
    estado_ingreso: string;
    fecha_recepcion: string | null;
};

/**
 * Traza de un equipo no recibido en el ingreso: la programación, si se re-agendó (y si
 * se cumplió), su seguimiento y lo que pasó después en los ingresos siguientes.
 */
export type TrazaNoRecibido = {
    programacion: {
        id: number;
        equipo_id: number;
        equipo: string;
        modelo: string;
        criticidad: CriticidadEquipo | null;
        tipo_servicio: string;
        fecha_programada: string | null;
        estado_programacion: string;
        novedad: string | null;
        observacion: string | null;
        ingreso: (IngresoTraza & { motivo_cancelacion: string | null }) | null;
    };
    re_agendamiento: {
        fecha: string | null;
        estado: 'CUMPLIDO' | 'PENDIENTE' | 'VENCIDO';
        /** Cuántas veces se re-agendó, siguiendo la cadena. */
        veces: number;
        /**
         * Cada programación que dejó un re-agendamiento, en orden (vacía en los re-agendados
         * de antes del enlace): la fecha que se eligió y qué pasó con ella.
         */
        cadena: {
            id: number;
            /** La fecha elegida al re-agendar. */
            fecha: string | null;
            estado_programacion: string;
            ingresado: boolean;
            novedad: string | null;
            observacion: string | null;
            ingreso: IngresoTraza | null;
            servicios: ServicioTraza[];
        }[];
    } | null;
    seguimientos: SeguimientoTraza[];
    posteriores: {
        id: number;
        estado_programacion: string;
        /** Si el equipo llegó a ese ingreso y el ingreso quedó recibido. */
        ingresado: boolean;
        novedad: string | null;
        ingreso: IngresoTraza;
        servicios: ServicioTraza[];
    }[];
};
