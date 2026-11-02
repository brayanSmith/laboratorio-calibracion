/** Opción de un filtro de los indicadores (técnico, empresa, tipo de equipo, área). */
export type IndicadorOption = {
    id: number;
    nombre: string;
};

/** Qué indicadores ve el usuario y las opciones de sus filtros (ver DashboardController). */
export type IndicadoresDashboard = {
    permisos: {
        tiempos_atencion: boolean;
        desempeno_tecnicos: boolean;
        cumplimiento: boolean;
    };
    tecnicos: IndicadorOption[];
    empresasTerceras: IndicadorOption[];
    tiposEquipo: IndicadorOption[];
    areas: IndicadorOption[];
};

/** Filtros de los indicadores; cada uno usa los que le aplican. */
export type FiltrosIndicador = {
    desde: string;
    hasta: string;
    /** "propio" o el id de una empresa tercera; vacío para todos. */
    laboratorio?: string;
    tecnico_id?: string;
    tipo_equipo_id?: string;
    area_id?: string;
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
};
