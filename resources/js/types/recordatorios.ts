export type FrecuenciaRecordatorio = 'SEMANAL' | 'MENSUAL';

export type Recordatorio = {
    activo: boolean;
    frecuencia: FrecuenciaRecordatorio;
    /** 1 = lunes ... 7 = domingo, si es semanal. */
    dia_semana: number | null;
    /** 1 a 28, si es mensual. */
    dia_mes: number | null;
    /** HH:MM en UTC. */
    hora_envio: string;
    /** Días hacia adelante que cuentan como "próximo a vencer". */
    dias_anticipacion: number;
    enviar_a_clientes: boolean;
    enviar_resumen_bahia: boolean;
    /** Correos adicionales que reciben el resumen de todas las bahías. */
    correos_copia: string[];
    ultimo_envio_at: string | null;
};

/** Lo que alcanzaría el recordatorio si se enviara ahora. */
export type ResumenRecordatorio = {
    vencidos: number;
    proximos_a_vencer: number;
    agendados: number;
    clientes: number;
    usuarios_bahia: number;
};
