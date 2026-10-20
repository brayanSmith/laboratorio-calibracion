export type Periodo =
    | 'TODO'
    | 'ULTIMOS_7_DIAS'
    | 'ULTIMO_MES'
    | 'ULTIMOS_3_MESES'
    | 'ULTIMO_ANIO'
    | 'PERSONALIZADO';

export const periodos: { value: Periodo; label: string }[] = [
    { value: 'TODO', label: 'Todo' },
    { value: 'ULTIMOS_7_DIAS', label: 'Últimos 7 días' },
    { value: 'ULTIMO_MES', label: 'Último mes' },
    { value: 'ULTIMOS_3_MESES', label: 'Últimos 3 meses' },
    { value: 'ULTIMO_ANIO', label: 'Último año' },
    { value: 'PERSONALIZADO', label: 'Personalizado' },
];

/** Rango de fechas (YYYY-MM-DD, ambos extremos incluidos); null = sin límite. */
export type RangoFechas = { desde: string | null; hasta: string | null };

/** Fecha local en formato YYYY-MM-DD. */
function formatear(fecha: Date): string {
    return fecha.toLocaleDateString('en-CA');
}

/**
 * Rango de fechas de un periodo, contado hacia atrás desde `hoy`. En PERSONALIZADO se usan
 * las fechas elegidas (vacías = sin límite).
 */
export function rangoDelPeriodo(
    periodo: Periodo,
    hoy: Date,
    personalizado: { desde: string; hasta: string } = { desde: '', hasta: '' },
): RangoFechas {
    if (periodo === 'TODO') {
        return { desde: null, hasta: null };
    }

    if (periodo === 'PERSONALIZADO') {
        return {
            desde: personalizado.desde || null,
            hasta: personalizado.hasta || null,
        };
    }

    const desde = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());

    if (periodo === 'ULTIMOS_7_DIAS') {
        desde.setDate(desde.getDate() - 6);
    } else if (periodo === 'ULTIMO_MES') {
        desde.setMonth(desde.getMonth() - 1);
    } else if (periodo === 'ULTIMOS_3_MESES') {
        desde.setMonth(desde.getMonth() - 3);
    } else {
        desde.setFullYear(desde.getFullYear() - 1);
    }

    return { desde: formatear(desde), hasta: formatear(hoy) };
}

/**
 * Si la fecha (YYYY-MM-DD) cae dentro del rango. Un registro sin fecha solo entra cuando el
 * rango no tiene límites.
 */
export function fechaEnRango(
    fecha: string | null | undefined,
    rango: RangoFechas,
): boolean {
    if (rango.desde === null && rango.hasta === null) {
        return true;
    }

    if (!fecha) {
        return false;
    }

    const dia = fecha.slice(0, 10);

    return (
        (rango.desde === null || dia >= rango.desde) &&
        (rango.hasta === null || dia <= rango.hasta)
    );
}
