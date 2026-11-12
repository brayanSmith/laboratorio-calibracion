import type { ReactNode } from 'react';

/**
 * Celda "Equipo" de las tablas: código y tipo arriba; modelo y número de serie debajo.
 * `codigo` puede ser un enlace (ej. a la ficha del equipo).
 */
export function CeldaEquipo({
    codigo,
    tipo,
    modelo,
    serie,
}: {
    codigo: ReactNode;
    tipo?: string | null;
    modelo?: string | null;
    serie?: string | null;
}) {
    return (
        <div className="min-w-40">
            <span data-test="equipo-codigo">
                <span className="font-medium">{codigo}</span>
                {tipo ? ` · ${tipo}` : ''}
            </span>
            <span className="block text-xs text-muted-foreground">
                Modelo: {modelo || '—'} · Serie: {serie || '—'}
            </span>
        </div>
    );
}

/** Celda "Área / Bahía": el área arriba y la bahía debajo. */
export function CeldaUbicacion({
    area,
    bahia,
}: {
    area?: string | null;
    bahia?: string | null;
}) {
    return (
        <div className="min-w-28">
            <span>{area || '—'}</span>
            <span className="block text-xs text-muted-foreground">
                Bahía: {bahia || '—'}
            </span>
        </div>
    );
}

/** Texto para ordenar y filtrar la celda "Equipo": busca en código, tipo, modelo y serie. */
export function textoEquipo(
    ...partes: (string | null | undefined)[]
): string {
    return partes.filter(Boolean).join(' · ');
}
