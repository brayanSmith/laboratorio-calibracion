import type { Equipo } from '@/types';

type Opcion = { id: string; label: string };

type EquipoDocumentable = Pick<
    Equipo,
    'tipo_equipo_id' | 'fabricante_id' | 'modelo' | 'tipo_equipo' | 'fabricante'
>;

const porNombre = (a: Opcion, b: Opcion) =>
    a.label.localeCompare(b.label, 'es');

/** Opciones únicas (por id) a partir de los equipos, ordenadas por nombre. */
function unicas(pares: Opcion[]): Opcion[] {
    return [...new Map(pares.map((par) => [par.id, par])).values()].sort(
        porNombre,
    );
}

/**
 * Listas dependientes para subir documentación por tipo de equipo, marca y modelo. Solo
 * ofrecen combinaciones que existen entre los equipos: los tipos de equipo, las marcas de
 * ese tipo y los modelos de esa marca y tipo.
 */
export function listasDocumentacionModelo(
    equipos: EquipoDocumentable[],
    seleccion: { tipoEquipoId: string; fabricanteId: string; modelo: string },
) {
    const deTipo = equipos.filter(
        (equipo) => String(equipo.tipo_equipo_id) === seleccion.tipoEquipoId,
    );
    const deMarca = deTipo.filter(
        (equipo) => String(equipo.fabricante_id) === seleccion.fabricanteId,
    );

    return {
        tipos: unicas(
            equipos.map((equipo) => ({
                id: String(equipo.tipo_equipo_id),
                label:
                    equipo.tipo_equipo?.nombre ??
                    `Tipo ${equipo.tipo_equipo_id}`,
            })),
        ),
        marcas: unicas(
            deTipo.map((equipo) => ({
                id: String(equipo.fabricante_id),
                label:
                    equipo.fabricante?.nombre ??
                    `Marca ${equipo.fabricante_id}`,
            })),
        ),
        modelos: unicas(
            deMarca.map((equipo) => ({
                id: equipo.modelo,
                label: equipo.modelo,
            })),
        ),
        /** Cuántos equipos recibirán la documentación con lo elegido. */
        coincidentes: deMarca.filter(
            (equipo) => equipo.modelo === seleccion.modelo,
        ).length,
    };
}
