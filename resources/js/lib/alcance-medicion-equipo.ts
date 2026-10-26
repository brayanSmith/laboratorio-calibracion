import type { EquipoAlcanceMedicionClave } from '@/types';

export type EstadoAlcanceMedicion = 'REGISTRADO' | 'PENDIENTE' | 'SIN_DATOS';

export const ETIQUETA_ALCANCE_MEDICION: Record<EstadoAlcanceMedicion, string> =
    {
        REGISTRADO: 'Registrado',
        PENDIENTE: 'Pendiente',
        SIN_DATOS: 'Sin especificación',
    };

export const BADGE_ALCANCE_MEDICION: Record<EstadoAlcanceMedicion, string> = {
    REGISTRADO: '!border-transparent !bg-emerald-500 !text-white',
    PENDIENTE: '!border-transparent !bg-amber-500 !text-white',
    SIN_DATOS: '',
};

const normalizar = (texto: string) => texto.trim().toLowerCase();

/**
 * Si el equipo ya tiene alcance de medición registrado para su tipo de equipo, modelo y
 * alcance de indicación (sin importar mayúsculas ni espacios al borde). `SIN_DATOS` si
 * falta alguno de los tres, porque todavía no se puede saber.
 */
export function estadoAlcanceMedicion(
    alcances: EquipoAlcanceMedicionClave[],
    tipoEquipoId: number | string | null | undefined,
    modelo: string | null | undefined,
    alcanceIndicacion: string | null | undefined,
): EstadoAlcanceMedicion {
    if (!tipoEquipoId || !modelo?.trim() || !alcanceIndicacion?.trim()) {
        return 'SIN_DATOS';
    }

    const registrado = alcances.some(
        (alcance) =>
            alcance.tipo_equipo_id.toString() === tipoEquipoId.toString() &&
            normalizar(alcance.modelo) === normalizar(modelo) &&
            normalizar(alcance.alcance_indicacion) ===
                normalizar(alcanceIndicacion),
    );

    return registrado ? 'REGISTRADO' : 'PENDIENTE';
}
