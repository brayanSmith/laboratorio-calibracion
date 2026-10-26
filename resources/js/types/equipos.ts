import type { Calibracion } from './calibraciones';
import type { Despacho } from './despachos';
import type { Ingreso, IngresoOption } from './ingresos';
import type { Mantenimiento, ServicioTerceroListado } from './mantenimientos';

export type TipoTecnologia = 'ANALOGICO' | 'DIGITAL';

export type EquipoOption = {
    id: number;
    nombre: string;
};

export type EquipoAreaOption = EquipoOption & {
    /** "CRC - Centro de Reparación de Componentes". */
    etiqueta: string;
};

export type EquipoTipoEquipoOption = EquipoOption & {
    /** A: requiere mantenimiento y calibración. B: solo mantenimiento. */
    tipo_mantenimiento: 'A' | 'B';
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
export type EstadoProgramacion =
    | 'PENDIENTE'
    | 'AGENDADO'
    | 'RECIBIDO'
    | 'CANCELADO'
    | 'RE_AGENDADO';
export type TipoMantenimientoProgramacion = 'PREVENTIVO' | 'CORRECTIVO';

export type DatosReAgendamiento = {
    fecha_proximo_agendamiento: string;
};

export type FaseSeguimiento =
    | 'BAHIA'
    | 'INGRESO'
    | 'MANTENIMIENTO'
    | 'CALIBRACION'
    | 'DESPACHO';

/** Una entrada del historial de una programación (más reciente primero). */
export type SeguimientoProgramacion = {
    id: number;
    fase: FaseSeguimiento;
    /** Estado de la fase al momento del registro, ej: EN_PROCESO. */
    seguimiento: string | null;
    observaciones: string | null;
    usuario: { id: number; name: string } | null;
    created_at: string;
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
    estado_programacion: EstadoProgramacion;
    /** false una vez que el equipo llegó (RECIBIDO): el servicio ya se gestionó. */
    eliminable: boolean;
    /** Historial de seguimiento, del más reciente al más antiguo. */
    seguimientos?: SeguimientoProgramacion[];
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
        /** Solo en la búsqueda del ingreso, que puede abarcar varias bahías. */
        bahia?: { nombre: string };
    };
};

/** Opción de equipo para agendar un mantenimiento correctivo desde un ingreso. */
export type EquipoDisponibleItem = {
    id: number;
    codigo: string;
    modelo: string;
    bahia: { nombre: string };
};

/** Equipo ya recibido (ingresado) y sin orden de trabajo agendada, para "Agendar
 * Mantenimiento". */
export type EquipoListoParaMantenimientoItem = {
    id: number;
    /** True si esta orden de trabajo nació de una calibración devuelta a mantenimiento. */
    devolucion: boolean;
    equipo: EquipoProgramacionBusquedaItem['equipo'];
};

/** Orden de trabajo con el mantenimiento finalizado, lista para "Agendar
 * Calibraciones". */
export type OrdenListaParaCalibracionItem = {
    id: number;
    equipo: EquipoProgramacionBusquedaItem['equipo'];
};

/** Despacho creado al finalizar una calibración, sin técnico de entrega todavía, para
 * "Agendar Despachos". */
export type DespachoListoItem = {
    id: number;
    entrega_autorizada: boolean;
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
    tipoEquipos: EquipoTipoEquipoOption[];
    fabricantes: EquipoOption[];
    areas: EquipoAreaOption[];
    bahias: EquipoBahiaOption[];
    clientes: EquipoOption[];
    tiposMagnitud: EquipoOption[];
    unidadesMedida: EquipoUnidadMedidaOption[];
    /** Alcances de medición registrados, para saber si un equipo ya tiene el suyo. */
    alcancesMedicion: EquipoAlcanceMedicionClave[];
};

/** Lo que identifica un alcance de medición: tipo de equipo, modelo y alcance de indicación. */
export type EquipoAlcanceMedicionClave = {
    tipo_equipo_id: number;
    modelo: string;
    alcance_indicacion: string;
};

export type EquipoPaginator = {
    data: Equipo[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};

/**
 * Historial de servicios de un equipo (ver EquipoController::historial()), del más reciente
 * al más antiguo. Cada registro trae los mismos datos que su listado, para abrir su modal "Ver".
 */
export type HistorialEquipo = {
    ingresos: Ingreso[];
    mantenimientos: Mantenimiento[];
    calibraciones: Calibracion[];
    despachos: Despacho[];
    servicios_terceros: ServicioTerceroListado[];
    historicos: EquipoHistorico[];
    /** Catálogo de Novedad (categoría INGRESO), que necesita la modal "Ver" del ingreso. */
    novedadesIngreso: IngresoOption[];
    /** Laboratorio propio de la Empresa, el de los históricos que no hizo un tercero. */
    laboratorioPropio: string | null;
    /** Empresas terceras, para elegirla al agregar un histórico de un tercero. */
    empresasTerceras: IngresoOption[];
};

/** Servicio anterior al sistema (o de otro sistema), importado desde un CSV. */
export type EquipoHistorico = {
    id: number;
    tipo_servicio: 'MANTENIMIENTO' | 'CALIBRACION';
    fecha_servicio: string;
    es_tercero: boolean;
    /** Solo si lo hizo un tercero. */
    empresa_tercero_id: number | null;
    empresa_tercero: string | null;
    adjunto_url: string | null;
    adjunto_nombre: string | null;
};
