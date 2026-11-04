import { normalizeText } from '@/lib/data-table';
import type { NavGroup, NavItem } from '@/types';

/** Mínimo de letras para buscar en la documentación de los equipos. */
export const MINIMO_BUSQUEDA_DOCUMENTOS = 2;

export type ModuloBuscable = NavItem & { grupo: string | null };

/**
 * Módulos del menú cuyo nombre o grupo contiene lo buscado (sin tildes ni mayúsculas).
 * Sin texto, todos los módulos.
 */
export function filtrarModulos(
    groups: NavGroup[],
    query: string,
): ModuloBuscable[] {
    const modulos = groups.flatMap((group) =>
        group.items.map((item) => ({ ...item, grupo: group.title ?? null })),
    );
    const buscado = normalizeText(query.trim());

    if (buscado === '') {
        return modulos;
    }

    return modulos.filter(
        (modulo) =>
            normalizeText(modulo.title).includes(buscado) ||
            normalizeText(modulo.grupo).includes(buscado),
    );
}

/** Ctrl + K (o ⌘ + K en Mac) abre el buscador desde cualquier pantalla. */
export function esAtajoBuscador(
    evento: Pick<KeyboardEvent, 'key' | 'ctrlKey' | 'metaKey'>,
): boolean {
    return (
        evento.key.toLowerCase() === 'k' && (evento.ctrlKey || evento.metaKey)
    );
}
