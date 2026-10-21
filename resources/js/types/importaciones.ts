export type TipoImportacion =
    | 'equipos'
    | 'items'
    | 'clientes'
    | 'fabricantes'
    | 'procedimientos-calibracion'
    | 'novedades'
    | 'laboratorios'
    | 'areas'
    | 'bahias'
    | 'empresas-terceras'
    | 'tipos-equipo'
    | 'checklist-mantenimiento';

export type EstadoImportacion =
    | 'PENDIENTE'
    | 'PROCESANDO'
    | 'COMPLETADA'
    | 'FALLIDA';

/** Campo del sistema que se relaciona con una columna del CSV. */
export type CampoImportacion = {
    clave: string;
    etiqueta: string;
    requerido: boolean;
    ayuda: string | null;
};

/** Respuesta al subir el CSV: sus columnas y lo necesario para relacionarlas. */
export type ArchivoImportacion = {
    tipo: TipoImportacion;
    tipo_etiqueta: string;
    /** Aviso para la modal de relacionar columnas (ej. qué catálogos se crean solos). */
    nota: string | null;
    archivo: string;
    nombre_original: string;
    encabezados: string[];
    vista_previa: string[][];
    campos: CampoImportacion[];
    sugerencias: Record<string, number | null>;
};

export type ResumenImportacion = {
    id: number;
    tipo: TipoImportacion;
    tipo_etiqueta: string;
    estado: EstadoImportacion;
    nombre_original: string;
    creada_en: string | null;
    /** Si el usuario ya revisó el resultado (ver el globito del botón Importar). */
    leida: boolean;
    total_filas: number;
    filas_procesadas: number;
    filas_creadas: number;
    filas_con_error: number;
    errores: { fila: number; mensaje: string }[];
};
