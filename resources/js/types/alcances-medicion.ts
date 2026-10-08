export type DetalleAlcanceMedicion = {
    id: number;
    unidad_medida_id: number;
    unidad_medida: string;
    unidad_medida_simbolo: string;
    valor_instrumento: string;
    emp: string;
    incertidumbre: string;
};

export type AlcanceMedicion = {
    id: number;
    tipo_equipo_id: number;
    tipo_equipo: string;
    alcance_indicacion: string;
    detalles: DetalleAlcanceMedicion[];
};

export type AlcanceMedicionOpcion = {
    id: number;
    nombre: string;
};

export type AlcanceMedicionUnidadOpcion = AlcanceMedicionOpcion & {
    simbolo: string;
};
