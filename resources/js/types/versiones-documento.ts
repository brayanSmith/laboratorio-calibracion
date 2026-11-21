/**
 * Un PDF emitido de un mantenimiento (informe) o de una calibración (certificado): el
 * original ("CC-000015") o una corrección ("CC-000015-A", "CC-000015-B", ...).
 */
export type VersionDocumento = {
    id: number;
    codigo: string;
    fecha: string | null;
    /** Quién hizo el cambio que generó la versión. */
    usuario: string | null;
    url: string;
};
