import type { CriticidadEquipo } from '@/types';

export const criticidadesEquipo: { value: CriticidadEquipo; label: string }[] =
    [
        { value: 'ALTA', label: 'Alta' },
        { value: 'MEDIA', label: 'Media' },
        { value: 'BAJA', label: 'Baja' },
    ];

/** Alta en rojo, media en amarillo, baja en verde. */
export const colorCriticidadEquipo: Record<CriticidadEquipo, string> = {
    ALTA: '!border-transparent !bg-red-500 !text-white',
    MEDIA: '!border-transparent !bg-amber-500 !text-white',
    BAJA: '!border-transparent !bg-emerald-500 !text-white',
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
