import type { ProgramacionPendienteBahia } from '@/types';

/**
 * Cuántos equipos distintos de cada bahía tienen una programación pendiente con fecha
 * entre `desde` y `hasta` (ambos incluidos, AAAA-MM-DD), igual que el buscador del
 * ingreso. Mientras falte alguna de las dos fechas, cuenta todas las pendientes.
 */
export function equiposPorBahia(
    programaciones: ProgramacionPendienteBahia[],
    desde: string,
    hasta: string,
): Map<number, number> {
    const conRango = desde !== '' && hasta !== '';
    const equipos = new Map<number, Set<number>>();

    for (const programacion of programaciones) {
        if (
            conRango &&
            (programacion.fecha < desde || programacion.fecha > hasta)
        ) {
            continue;
        }

        const deLaBahia = equipos.get(programacion.bahia_id) ?? new Set();
        deLaBahia.add(programacion.equipo_id);
        equipos.set(programacion.bahia_id, deLaBahia);
    }

    return new Map(
        [...equipos].map(([bahiaId, deLaBahia]) => [bahiaId, deLaBahia.size]),
    );
}
