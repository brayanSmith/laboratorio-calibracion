export type TipoTecnologia = 'ANALOGICO' | 'DIGITAL';

export type EquipoOption = {
    id: number;
    nombre: string;
};

export type EquipoUnidadMedidaOption = EquipoOption & {
    simbolo: string;
};

export type EquipoBahiaOption = EquipoOption & {
    area_id: number;
};

export type EquipoEspecificacionTecnica = {
    id: number;
    tipo_magnitud_id: number;
    unidad_medida_id: number;
    alcance_indicacion: string;
    precision: string;
    resolucion: string;
};

export type TipoServicio = 'MANTENIMIENTO' | 'CALIBRACION';
export type EstadoVencimiento = 'AL_DIA' | 'PROXIMO_A_VENCER' | 'VENCIDO';
export type IntervaloUnidad = 'DIAS' | 'SEMANAS' | 'MESES';
export type EstadoProgramacion = 'PENDIENTE' | 'AGENDADO' | 'CANCELADO';
export type TipoMantenimientoProgramacion = 'PREVENTIVO' | 'CORRECTIVO';

export type DatosReAgendamiento = {
    fecha_proximo_agendamiento: string;
};

export type EquipoProgramacion = {
    id: number;
    /** CSV de uno o varios TipoServicio, ej: "MANTENIMIENTO,CALIBRACION". */
    tipo_servicio: string;
    tipo_mantenimiento: TipoMantenimientoProgramacion;
    /** Solo cuando tipo_mantenimiento es CORRECTIVO. */
    falla_detectada: string | null;
    intervalo_servicio: string | null;
    intervalo_unidad: IntervaloUnidad | null;
    fecha_ultimo_servicio: string | null;
    /** Calculada: fecha_ultimo_servicio + intervalo_servicio. */
    fecha_proximo_servicio: string | null;
    dias_plazo_vencimiento: string;
    /** Calculado a partir de fecha_proximo_servicio y dias_plazo_vencimiento. */
    estado_vencimiento: EstadoVencimiento;
};

/**
 * Resultado de buscar programaciones de servicio por rango de fechas y bahía, o una ya
 * vinculada a un ingreso (ver Ingreso.equipo_programaciones).
 */
export type EquipoProgramacionBusquedaItem = {
    id: number;
    tipo_servicio: string;
    tipo_mantenimiento: TipoMantenimientoProgramacion;
    /** Solo cuando tipo_mantenimiento es CORRECTIVO. */
    falla_detectada: string | null;
    /** Nulo cuando tipo_mantenimiento es CORRECTIVO: no tiene fecha programada. */
    fecha_proximo_servicio: string | null;
    estado_vencimiento: EstadoVencimiento;
    estado_programacion: EstadoProgramacion;
    /** Si la persona decide agendar este equipo al revisarlo en el ingreso. */
    agendar: boolean;
    /** Si el equipo efectivamente llegó, al recibir el ingreso. */
    ingresado: boolean;
    /** FK al catálogo de Novedad (categoría INGRESO). */
    novedad_ingreso_id: number | null;
    observacion_no_ingreso: string | null;
    re_agendar: boolean;
    datos_re_agendamiento: DatosReAgendamiento | null;
    equipo: {
        id: number;
        codigo: string;
        modelo: string;
        tipo_equipo: { nombre: string };
        cliente: { nombre: string } | null;
    };
};

/** Opción de equipo para agendar un mantenimiento correctivo desde un ingreso. */
export type EquipoDisponibleItem = {
    id: number;
    codigo: string;
    modelo: string;
};

/** Equipo ya recibido (ingresado) y sin orden de trabajo, para "Agendar Mantenimiento". */
export type EquipoListoParaMantenimientoItem = {
    id: number;
    equipo: EquipoProgramacionBusquedaItem['equipo'];
};

/** Orden de trabajo con el mantenimiento finalizado, lista para "Agendar
 * Calibraciones". */
export type OrdenListaParaCalibracionItem = {
    id: number;
    equipo: EquipoProgramacionBusquedaItem['equipo'];
};

export type DocumentoEquipoItem = {
    id: number;
    nombre: string;
    archivo_url: string;
};

export type EquipoFichaTecnica = {
    pais_procedencia: string;
    numero_activo: string;
    proveedor: string;
    costo_usd: number;
    fecha_adquisicion: string;
};

export type Equipo = {
    id: number;
    codigo: string;
    tipo_equipo_id: number;
    tipo_tecnologia: TipoTecnologia;
    modelo: string;
    fabricante_id: number;
    numero_serie: string;
    area_id: number;
    bahia_id: number;
    condicion_actual: string;
    notas: string | null;
    activo: boolean;
    patron_referencia: boolean;
    concatenar_codigo_nombre: string | null;
    requiere_programacion: boolean;
    cliente_id: number;
    ficha_tecnica: EquipoFichaTecnica | null;
    tipo_equipo?: EquipoOption;
    fabricante?: EquipoOption;
    area?: EquipoOption;
    bahia?: EquipoOption;
    cliente?: EquipoOption;
    equipo_especificacion_tecnica?: EquipoEspecificacionTecnica | null;
    equipo_programaciones?: EquipoProgramacion[];
    equipo_documentos?: DocumentoEquipoItem[];
};

export type EquipoFormOptions = {
    tipoEquipos: EquipoOption[];
    fabricantes: EquipoOption[];
    areas: EquipoOption[];
    bahias: EquipoBahiaOption[];
    clientes: EquipoOption[];
    tiposMagnitud: EquipoOption[];
    unidadesMedida: EquipoUnidadMedidaOption[];
};

export type EquipoPaginator = {
    data: Equipo[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};
