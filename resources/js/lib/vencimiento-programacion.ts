import type { EquipoProgramacion, EstadoVencimiento } from '@/types';

/** Color del badge de vencimiento: verde al día, ámbar próximo a vencer, rojo vencido. */
export const BADGE_VENCIMIENTO: Record<EstadoVencimiento, string> = {
    AL_DIA: '!border-transparent !bg-emerald-500 !text-white',
    PROXIMO_A_VENCER: '!border-transparent !bg-amber-500 !text-white',
    VENCIDO: '!border-transparent !bg-red-500 !text-white',
};

const GRAVEDAD: Record<EstadoVencimiento, number> = {
    AL_DIA: 0,
    PROXIMO_A_VENCER: 1,
    VENCIDO: 2,
};

/**
 * Una programación con el equipo ya recibido (RECIBIDO) ya se está gestionando o se
 * gestionó: su vencimiento deja de importar.
 */
export function estaGestionada(programacion: EquipoProgramacion): boolean {
    return programacion.estado_programacion === 'RECIBIDO';
}

/**
 * Vencimiento más urgente entre las programaciones pendientes de gestionar del equipo
 * (vencido > próximo a vencer > al día) y la fecha más cercana de ese estado. Es nulo si
 * el equipo no tiene ninguna programación por atender.
 */
export function vencimientoDelEquipo(
    programaciones: EquipoProgramacion[] | undefined,
): { estado: EstadoVencimiento; fecha: string | null } | null {
    const pendientes = (programaciones ?? []).filter(
        (programacion) => !estaGestionada(programacion),
    );

    if (pendientes.length === 0) {
        return null;
    }

    const estado = pendientes.reduce<EstadoVencimiento>(
        (peor, actual) =>
            GRAVEDAD[actual.estado_vencimiento] > GRAVEDAD[peor]
                ? actual.estado_vencimiento
                : peor,
        'AL_DIA',
    );

    const fechas = pendientes
        .filter((programacion) => programacion.estado_vencimiento === estado)
        .map((programacion) => programacion.fecha_proximo_servicio)
        .filter((fecha): fecha is string => fecha !== null)
        .sort();

    return { estado, fecha: fechas[0]?.slice(0, 10) ?? null };
}
