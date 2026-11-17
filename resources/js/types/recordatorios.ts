export type FrecuenciaRecordatorio = 'SEMANAL' | 'MENSUAL';

/** El mes que avisa el envío mensual: el mismo en que sale o el siguiente. */
export type MesAvisoRecordatorio = 'ACTUAL' | 'SIGUIENTE';

/** cliente: el correo a cada cliente; bahia: el resumen por bahía y los correos con copia. */
export type TipoPlantillaRecordatorio = 'cliente' | 'bahia';

export type PlantillaRecordatorio = {
    asunto: string;
    mensaje: string;
};

/** Una plantilla creada por el tenant (ej. "Navidad"). */
export type PlantillaPersonalizada = PlantillaRecordatorio & {
    id: number;
    nombre: string;
};

/** Las plantillas de un correo: la predeterminada, las creadas y cuál está en uso. */
export type PlantillasCorreo = {
    /** Id de la plantilla en uso; null si es la predeterminada. */
    en_uso: number | null;
    predeterminada: PlantillaRecordatorio;
    personalizadas: PlantillaPersonalizada[];
};

export type Recordatorio = {
    activo: boolean;
    frecuencia: FrecuenciaRecordatorio;
    /** 1 = lunes ... 7 = domingo, si es semanal. */
    dia_semana: number | null;
    /** 1 a 28, si es mensual. */
    dia_mes: number | null;
    /** Si es mensual: el mes que avisa, hasta su último día. */
    mes_aviso: MesAvisoRecordatorio;
    /** HH:MM en UTC. */
    hora_envio: string;
    /** Si es semanal: días hacia adelante que cuentan como "próximo a vencer". */
    dias_anticipacion: number;
    enviar_a_clientes: boolean;
    enviar_resumen_bahia: boolean;
    /** Correos adicionales que reciben el resumen de todas las bahías. */
    correos_copia: string[];
    ultimo_envio_at: string | null;
};

/** Lo que alcanzaría el recordatorio si se enviara ahora. */
export type ResumenRecordatorio = {
    /** Rango (YYYY-MM-DD) de vencimientos que avisaría, además de los vencidos. */
    avisa_desde: string;
    avisa_hasta: string;
    vencidos: number;
    /** Los que vencen dentro del rango (avisa_desde a avisa_hasta), agendados o no. */
    programados: number;
    clientes: number;
    usuarios_bahia: number;
};

/** Un cliente que recibiría el correo, para ver la vista previa de su PDF. */
export type ClienteRecordatorio = {
    id: number;
    nombre: string;
    /** Sin correo registrado no se le envía nada. */
    tiene_correo: boolean;
    equipos: number;
};

/** Un correo del historial de envíos del recordatorio. */
export type EnvioRecordatorio = {
    id: number;
    /** copia: los correos "con copia a". */
    tipo: TipoPlantillaRecordatorio | 'copia';
    /** Enviado con "Enviarme una prueba". */
    es_prueba: boolean;
    destinatario: string;
    correo: string;
    asunto: string;
    equipos: {
        codigo: string;
        tipo_equipo: string;
        cliente: string;
        bahia: string;
        fecha_proximo_servicio: string;
        /** PROXIMO_A_VENCER y AGENDADO solo en envíos anteriores a "Programados". */
        estado: 'VENCIDO' | 'PROGRAMADO' | 'PROXIMO_A_VENCER' | 'AGENDADO';
    }[];
    estado: 'EN_COLA' | 'ENVIADO' | 'FALLIDO';
    /** Por qué no se pudo enviar. */
    error: string | null;
    creado_at: string;
    enviado_at: string | null;
};

export type EnviosRecordatorioPaginator = {
    data: EnvioRecordatorio[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};
