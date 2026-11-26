/** Un equipo del cliente en el portal, con su estado (ver PortalEquipoController::index()). */
export type EquipoPortal = {
    id: number;
    codigo: string;
    tipo_equipo: string | null;
    clase_equipo: string | null;
    modelo: string | null;
    numero_serie: string | null;
    area: string | null;
    bahia: string | null;
    activo: boolean;
    estado_vencimiento:
        | 'AL_DIA'
        | 'PROXIMO_A_VENCER'
        | 'VENCIDO'
        | 'SIN_PROGRAMACION'
        | 'INACTIVO';
    fecha_proximo_servicio: string | null;
};

/** Servicio registrado antes de usar el sistema (importado o agregado a mano). */
export type HistoricoPortal = {
    id: number;
    tipo_servicio: string;
    fecha_servicio: string;
    es_tercero: boolean;
    empresa_tercero: string | null;
    adjunto_url: string | null;
    adjunto_nombre: string | null;
};
