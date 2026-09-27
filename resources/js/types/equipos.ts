export type TipoTecnologia = 'ANALOGICO' | 'DIGITAL';

export type EquipoOption = {
    id: number;
    nombre: string;
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

export type EquipoProgramacion = {
    id: number;
    tipo_servicio: TipoServicio;
    intervalo_servicio: string | null;
    fecha_apertura_historial_servicio: string | null;
    fecha_ultimo_servicio: string | null;
    fecha_proximo_servicio: string | null;
    dias_plazo_vencimiento: string;
    estado_vencimiento: EstadoVencimiento;
};

export type DocumentoEquipoItem = {
    id: number;
    nombre: string;
    archivo_url: string;
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
    bahias: EquipoOption[];
    clientes: EquipoOption[];
    tiposMagnitud: EquipoOption[];
    unidadesMedida: EquipoOption[];
};

export type EquipoPaginator = {
    data: Equipo[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};
