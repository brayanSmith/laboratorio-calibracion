export type TipoImportacion =
    | 'equipos'
    | 'items'
    | 'clientes'
    | 'fabricantes'
    | 'procedimientos-calibracion'
    | 'novedades'
    | 'areas'
    | 'bahias'
    | 'bahia-usuarios'
    | 'empresas-terceras'
    | 'tipos-equipo'
    | 'checklist-mantenimiento'
    | 'equipo-historicos';

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
    /** Campo que identifica un registro que ya existe (ej. el código del ítem): antes de importar se elige si reemplazarlo u omitirlo. Null si los repetidos solo se omiten. */
    campo_existente: string | null;
    sugerencias: Record<string, number | null>;
    /** Si el tipo acepta una carpeta de adjuntos (las filas los nombran), sus límites; si no, null. */
    adjuntos: ConfiguracionAdjuntos | null;
};

/** Cómo se suben los adjuntos de una importación (ver SubirAdjuntosImportacionRequest). */
export type ConfiguracionAdjuntos = {
    extensiones: string[];
    archivos_por_tanda: number;
    tamanio_maximo_kb: number;
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
    /** Registros que ya existían y se reemplazaron con los datos del archivo. */
    filas_actualizadas: number;
    filas_con_error: number;
    errores: { fila: number; mensaje: string }[];
};
