/**
 * Plazo de un servicio (mantenimiento, calibración o servicio de tercero) frente a la fecha
 * para la que se programó. Mientras no se termina: cuántos días faltan, si vence hoy o si
 * ya está vencido. Una vez terminado: si se hizo a tiempo o con cuántos días de retraso.
 */
export type PlazoServicio =
    | { estado: 'PENDIENTE'; dias: number }
    | { estado: 'VENCE_HOY'; dias: 0 }
    | { estado: 'VENCIDO'; dias: number }
    | { estado: 'A_TIEMPO'; dias: number }
    | { estado: 'TARDE'; dias: number };

/** Días entre dos fechas AAAA-MM-DD (b - a), sin que la hora o el huso horario influyan. */
function diasEntre(a: string, b: string): number {
    const [anioA, mesA, diaA] = a.split('-').map(Number);
    const [anioB, mesB, diaB] = b.split('-').map(Number);

    return Math.round(
        (Date.UTC(anioB, mesB - 1, diaB) - Date.UTC(anioA, mesA - 1, diaA)) /
            86_400_000,
    );
}

/** Hoy en formato AAAA-MM-DD, en la hora local. */
export function hoyLocal(): string {
    const fecha = new Date();
    fecha.setMinutes(fecha.getMinutes() - fecha.getTimezoneOffset());

    return fecha.toISOString().slice(0, 10);
}

export function plazoServicio(
    fechaProgramada: string | null,
    fechaRealizacion: string | null,
    hoy: string = hoyLocal(),
): PlazoServicio | null {
    if (!fechaProgramada) {
        return null;
    }

    if (fechaRealizacion) {
        const retraso = diasEntre(fechaProgramada, fechaRealizacion);

        return retraso > 0
            ? { estado: 'TARDE', dias: retraso }
            : { estado: 'A_TIEMPO', dias: Math.abs(retraso) };
    }

    const faltan = diasEntre(hoy, fechaProgramada);

    if (faltan > 0) {
        return { estado: 'PENDIENTE', dias: faltan };
    }

    return faltan === 0
        ? { estado: 'VENCE_HOY', dias: 0 }
        : { estado: 'VENCIDO', dias: -faltan };
}

const dias = (n: number) => `${n} ${n === 1 ? 'día' : 'días'}`;

export function etiquetaPlazo(plazo: PlazoServicio): string {
    switch (plazo.estado) {
        case 'PENDIENTE':
            return `Faltan ${dias(plazo.dias)}`;
        case 'VENCE_HOY':
            return 'Vence hoy';
        case 'VENCIDO':
            return `Vencido hace ${dias(plazo.dias)}`;
        case 'A_TIEMPO':
            return 'A tiempo';
        case 'TARDE':
            return `${dias(plazo.dias)} tarde`;
    }
}

/** Pendiente en azul, vence hoy en ámbar, vencido o tarde en rojo, a tiempo en verde. */
export const BADGE_PLAZO: Record<PlazoServicio['estado'], string> = {
    PENDIENTE: '!border-transparent !bg-blue-500 !text-white',
    VENCE_HOY: '!border-transparent !bg-amber-500 !text-white',
    VENCIDO: '!border-transparent !bg-red-500 !text-white',
    A_TIEMPO: '!border-transparent !bg-emerald-500 !text-white',
    TARDE: '!border-transparent !bg-red-500 !text-white',
};
