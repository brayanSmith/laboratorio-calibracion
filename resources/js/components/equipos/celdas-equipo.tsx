import type { ReactNode } from 'react';

/**
 * Celda "Equipo" de las tablas: código, tipo y su clase de mantenimiento (A o B) arriba;
 * modelo y número de serie debajo. `codigo` puede ser un enlace (ej. a la ficha del equipo).
 */
export function CeldaEquipo({
    codigo,
    tipo,
    clase,
    modelo,
    serie,
}: {
    codigo: ReactNode;
    tipo?: string | null;
    /** Tipo de mantenimiento del tipo de equipo: A (mantenimiento y calibración) o B (solo mantenimiento). */
    clase?: string | null;
    modelo?: string | null;
    serie?: string | null;
}) {
    return (
        <div className="min-w-40">
            <span data-test="equipo-codigo">
                <span className="font-medium">{codigo}</span>
                {tipo ? ` · ${tipo}` : ''}
            </span>
            {clase ? (
                <span
                    className="ml-1.5 inline-flex items-center rounded border px-1.5 text-[10px] leading-4 font-semibold whitespace-nowrap text-muted-foreground"
                    title={
                        clase === 'A'
                            ? 'Tipo A: mantenimiento y calibración'
                            : 'Tipo B: solo mantenimiento'
                    }
                    data-test="equipo-clase"
                >
                    Tipo {clase}
                </span>
            ) : null}
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
export function textoEquipo(...partes: (string | null | undefined)[]): string {
    return partes.filter(Boolean).join(' · ');
}
