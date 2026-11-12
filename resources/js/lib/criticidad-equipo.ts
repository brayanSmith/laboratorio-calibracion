import type { CriticidadEquipo } from '@/types';

export const criticidadesEquipo: { value: CriticidadEquipo; label: string }[] =
    [
        { value: 'ALTA', label: 'Alta' },
        { value: 'MEDIA', label: 'Media' },
        { value: 'BAJA', label: 'Baja' },
    ];

/**
 * Alta en rojo, media en amarillo, baja en verde: fondo tenue con letras oscuras (y borde
 * del mismo tono), para distinguirlo de los badges de estado, que van con fondo fuerte.
 */
export const colorCriticidadEquipo: Record<CriticidadEquipo, string> = {
    ALTA: '!border-red-300 !bg-red-100 !text-red-800 dark:!border-red-800 dark:!bg-red-950/60 dark:!text-red-200',
    MEDIA: '!border-amber-300 !bg-amber-100 !text-amber-800 dark:!border-amber-800 dark:!bg-amber-950/60 dark:!text-amber-200',
    BAJA: '!border-emerald-300 !bg-emerald-100 !text-emerald-800 dark:!border-emerald-800 dark:!bg-emerald-950/60 dark:!text-emerald-200',
};

/** Etiqueta de la criticidad, ej. `ALTA` → "Alta". */
export function etiquetaCriticidadEquipo(
    criticidad: CriticidadEquipo | null,
): string | null {
    return (
        criticidadesEquipo.find((opcion) => opcion.value === criticidad)
            ?.label ?? null
    );
}

/** Orden de la criticidad para ordenar listas: alta, media, baja y, al final, sin asignar. */
export function rangoCriticidadEquipo(
    criticidad: CriticidadEquipo | null,
): number {
    const indice = criticidadesEquipo.findIndex(
        (opcion) => opcion.value === criticidad,
    );

    return indice === -1 ? criticidadesEquipo.length : indice;
}
