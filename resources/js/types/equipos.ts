export type TipoTecnologia = 'ANALOGICO' | 'DIGITAL';

export type EquipoOption = {
    id: number;
    nombre: string;
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
};

export type EquipoFormOptions = {
    tipoEquipos: EquipoOption[];
    fabricantes: EquipoOption[];
    areas: EquipoOption[];
    bahias: EquipoOption[];
    clientes: EquipoOption[];
};

export type EquipoPaginator = {
    data: Equipo[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};
