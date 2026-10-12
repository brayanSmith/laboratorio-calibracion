const HEX_COLOR = /^#[0-9a-fA-F]{6}$/;

export function isHexColor(value: string | null | undefined): value is string {
    return typeof value === 'string' && HEX_COLOR.test(value);
}

/** Blanco o casi negro, según cuál se lee mejor sobre el color de fondo dado. */
export function contrastForeground(hex: string): string {
    const red = parseInt(hex.slice(1, 3), 16);
    const green = parseInt(hex.slice(3, 5), 16);
    const blue = parseInt(hex.slice(5, 7), 16);
    const luminance = (0.299 * red + 0.587 * green + 0.114 * blue) / 255;

    return luminance > 0.6 ? '#111111' : '#ffffff';
}

/**
 * Variables CSS que reemplazan el tema predeterminado con los colores de la empresa.
 * `colorTema` tiñe botones y acentos; `colorNav` la barra de navegación lateral.
 */
export function empresaCssVariables(
    colorTema: string | null | undefined,
    colorNav: string | null | undefined,
): Record<string, string> {
    const variables: Record<string, string> = {};

    if (isHexColor(colorTema)) {
        variables['--primary'] = colorTema;
        variables['--primary-foreground'] = contrastForeground(colorTema);
        variables['--ring'] = colorTema;
    }

    if (isHexColor(colorNav)) {
        const foreground = contrastForeground(colorNav);

        variables['--sidebar'] = colorNav;
        variables['--sidebar-foreground'] = foreground;
        variables['--sidebar-accent'] =
            `color-mix(in srgb, ${foreground} 14%, ${colorNav})`;
        variables['--sidebar-accent-foreground'] = foreground;
        variables['--sidebar-border'] =
            `color-mix(in srgb, ${foreground} 20%, ${colorNav})`;
        variables['--sidebar-primary'] = foreground;
        variables['--sidebar-primary-foreground'] = colorNav;
    }

    return variables;
}

/** Colores sugeridos para el tema: botones y acentos. */
export const COLORES_TEMA = [
    { nombre: 'Azul', valor: '#1d4ed8' },
    { nombre: 'Celeste', valor: '#0284c7' },
    { nombre: 'Turquesa', valor: '#0d9488' },
    { nombre: 'Verde', valor: '#16a34a' },
    { nombre: 'Lima', valor: '#65a30d' },
    { nombre: 'Naranja', valor: '#ea580c' },
    { nombre: 'Rojo', valor: '#dc2626' },
    { nombre: 'Rosa', valor: '#db2777' },
    { nombre: 'Violeta', valor: '#7c3aed' },
    { nombre: 'Gris oscuro', valor: '#374151' },
];

/** Colores sugeridos para la barra de navegación: tonos que dejan leer el menú. */
export const COLORES_NAV = [
    { nombre: 'Negro', valor: '#111827' },
    { nombre: 'Azul noche', valor: '#0f172a' },
    { nombre: 'Azul marino', valor: '#1e3a8a' },
    { nombre: 'Verde bosque', valor: '#14532d' },
    { nombre: 'Verde petróleo', valor: '#134e4a' },
    { nombre: 'Vino', valor: '#7f1d1d' },
    { nombre: 'Morado', valor: '#4c1d95' },
    { nombre: 'Gris', valor: '#e5e7eb' },
    { nombre: 'Celeste claro', valor: '#e0f2fe' },
    { nombre: 'Blanco', valor: '#ffffff' },
];
