export const periodosIndicador = [
    { value: 'hoy', label: 'Hoy' },
    { value: 'ayer', label: 'Ayer' },
    { value: 'ultima_semana', label: 'Última semana' },
    { value: 'ultimo_mes', label: 'Último mes' },
    { value: 'ultimo_trimestre', label: 'Último trimestre' },
    { value: 'ultimo_anio', label: 'Último año' },
    { value: 'personalizado', label: 'Personalizado' },
] as const;

export type PeriodoIndicador = (typeof periodosIndicador)[number]['value'];

/** Periodo con el que abren los indicadores. */
export const PERIODO_POR_DEFECTO: PeriodoIndicador = 'ultimo_mes';

/** Fecha local en formato AAAA-MM-DD. */
function aFecha(fecha: Date): string {
    return fecha.toLocaleDateString('en-CA');
}

/** La misma fecha `meses` atrás; si ese día no existe (31 de febrero) toma el último del mes. */
function restarMeses(fecha: Date, meses: number): Date {
    const resultado = new Date(
        fecha.getFullYear(),
        fecha.getMonth() - meses,
        1,
    );
    const ultimoDia = new Date(
        resultado.getFullYear(),
        resultado.getMonth() + 1,
        0,
    ).getDate();
    resultado.setDate(Math.min(fecha.getDate(), ultimoDia));

    return resultado;
}

/**
 * Rango de fechas (AAAA-MM-DD) de un periodo, contado hacia atrás desde hoy. "Personalizado"
 * no tiene rango propio: devuelve null para conservar el que el usuario elija.
 */
export function rangoDePeriodo(
    periodo: PeriodoIndicador,
    hoy: Date = new Date(),
): { desde: string; hasta: string } | null {
    const dia = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());
    const haceDias = (dias: number) =>
        new Date(dia.getFullYear(), dia.getMonth(), dia.getDate() - dias);

    switch (periodo) {
        case 'hoy':
            return { desde: aFecha(dia), hasta: aFecha(dia) };
        case 'ayer':
            return { desde: aFecha(haceDias(1)), hasta: aFecha(haceDias(1)) };
        case 'ultima_semana':
            return { desde: aFecha(haceDias(7)), hasta: aFecha(dia) };
        case 'ultimo_mes':
            return { desde: aFecha(restarMeses(dia, 1)), hasta: aFecha(dia) };
        case 'ultimo_trimestre':
            return { desde: aFecha(restarMeses(dia, 3)), hasta: aFecha(dia) };
        case 'ultimo_anio':
            return { desde: aFecha(restarMeses(dia, 12)), hasta: aFecha(dia) };
        case 'personalizado':
            return null;
    }
}

/** Rango del periodo por defecto, para el estado inicial de los filtros. */
export function rangoPorDefecto(): { desde: string; hasta: string } {
    return rangoDePeriodo(PERIODO_POR_DEFECTO) as {
        desde: string;
        hasta: string;
    };
}

/** 16.58 -> "16 h 35 min"; 0.5 -> "30 min"; nulo -> "—". */
export function formatearHoras(horas: number | null): string {
    if (horas === null) {
        return '—';
    }

    const totalMinutos = Math.round(horas * 60);
    const h = Math.floor(totalMinutos / 60);
    const min = totalMinutos % 60;

    if (h === 0) {
        return `${min} min`;
    }

    return min === 0 ? `${h} h` : `${h} h ${min} min`;
}

/** 3.5 -> "3,5 días"; 1 -> "1 día"; nulo -> "—". */
export function formatearDias(dias: number | null): string {
    if (dias === null) {
        return '—';
    }

    const texto = dias.toLocaleString('es-CO', { maximumFractionDigits: 1 });

    return `${texto} ${dias === 1 ? 'día' : 'días'}`;
}

/** 25 -> "25 %"; nulo -> "—". */
export function formatearPorcentaje(porcentaje: number | null): string {
    return porcentaje === null
        ? '—'
        : `${porcentaje.toLocaleString('es-CO', { maximumFractionDigits: 1 })} %`;
}

/** Valor de un filtro: un texto o, en los de selección múltiple, una lista. */
export type ValorFiltro = string | string[] | undefined;

/** Quita los filtros vacíos (y las listas sin elementos), para no enviarlos en la URL. */
export function filtrosConValor<T extends Record<string, ValorFiltro>>(
    filtros: T,
): Record<string, string | string[]> {
    return Object.fromEntries(
        Object.entries(filtros).filter(
            (entrada): entrada is [string, string | string[]] =>
                entrada[1] !== undefined &&
                entrada[1] !== '' &&
                !(Array.isArray(entrada[1]) && entrada[1].length === 0),
        ),
    );
}

/**
 * Tono (HSL) del mapa de calor para un valor dentro del rango [mínimo, máximo]: verde
 * (120) en el mínimo, amarillo (50) a la mitad y rojo (0) en el máximo. Si todos los
 * valores son iguales, verde.
 */
export function tonoMapaCalor(
    valor: number,
    minimo: number,
    maximo: number,
): number {
    if (maximo <= minimo) {
        return 120;
    }

    const proporcion = Math.min(
        1,
        Math.max(0, (valor - minimo) / (maximo - minimo)),
    );

    return proporcion <= 0.5
        ? Math.round(120 - (proporcion / 0.5) * 70)
        : Math.round(50 - ((proporcion - 0.5) / 0.5) * 50);
}

/**
 * Opciones de filtros dependientes, por filtro: cada filtro se llama igual que su lista de
 * opciones (ej. `laboratorios`, `tecnicos`, `tipos_equipo`).
 */
export type OpcionesDependientes<K extends string = string> = Record<
    K,
    { id: number | string }[]
>;

/**
 * Quita de cada filtro de selección múltiple los valores que ya no están en su lista (por
 * ejemplo, al cambiar el periodo o un filtro anterior). Sirve para cualquier módulo: revisa
 * cada lista de `opciones` contra el filtro del mismo nombre. Devuelve null si todo lo
 * elegido sigue siendo válido.
 */
export function depurarFiltrosDependientes<
    T extends Record<string, ValorFiltro>,
>(filtros: T, opciones: OpcionesDependientes): T | null {
    let cambio = false;
    const depurados: Record<string, ValorFiltro> = { ...filtros };

    Object.entries(opciones).forEach(([campo, lista]) => {
        const elegidos = filtros[campo];

        if (!Array.isArray(elegidos)) {
            return;
        }

        const validos = new Set(lista.map((opcion) => String(opcion.id)));
        const quedan = elegidos.filter((valor) => validos.has(valor));

        if (quedan.length !== elegidos.length) {
            depurados[campo] = quedan;
            cambio = true;
        }
    });

    return cambio ? (depurados as T) : null;
}

/** 12 -> "12"; 3.75 -> "3,8" (los objetivos mensuales de cantidad salen del anual ÷ 12). */
export function formatearCantidad(cantidad: number): string {
    return cantidad.toLocaleString('es-CO', { maximumFractionDigits: 1 });
}
