export type DetalleAlcanceMedicion = {
    id: number;
    unidad_medida_id: number;
    unidad_medida: string;
    unidad_medida_simbolo: string;
    valor_referencia: string;
    emp: string | null;
    emp_porcentaje: string | null;
    incertidumbre: string;
};

export type AlcanceMedicion = {
    id: number;
    tipo_equipo_id: number;
    tipo_equipo: string;
    modelo: string;
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

/** Precisión ("±0.5" o "0.5%") y unidad de medida de los equipos de un alcance. */
export type EspecificacionAlcance = {
    precision: string;
    unidad_medida_id: number;
};

/** Especificaciones por "tipo|modelo|alcance de indicación". */
export type EspecificacionesAlcance = Record<string, EspecificacionAlcance>;

export type AlcancesIndicacionPorModelo = Record<
    string,
    Record<string, string[]>
>;
