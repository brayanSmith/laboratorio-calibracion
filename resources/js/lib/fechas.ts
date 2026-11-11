/**
 * Formato de fechas de todo el sistema: DD/MM/AAAA (y DD/MM/AAAA HH:mm con hora), para
 * que nunca se confunda con AAAA/MM/DD. Las fechas llegan del servidor como "AAAA-MM-DD"
 * (o ISO 8601 con hora); se guardan y se envían así, solo se formatean al mostrarlas.
 */

/** "2026-11-04" o "2026-11-04 00:00:00" (sin zona: se toma tal cual, sin correr el día). */
const SOLO_FECHA = /^(\d{4})-(\d{2})-(\d{2})(?:[ T]00:00(?::00(?:\.0+)?)?Z?)?$/;

/** ISO 8601 con hora, ej. "2026-11-04T15:30:00+00:00" o "2026-11-04T15:30:00.000000Z". */
const FECHA_CON_HORA = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/;

const dosDigitos = (numero: number) => String(numero).padStart(2, '0');

/** Si el texto es una fecha (con o sin hora) que se pueda mostrar en DD/MM/AAAA. */
export function esFecha(valor: unknown): valor is string {
    return (
        typeof valor === 'string' &&
        (SOLO_FECHA.test(valor) || FECHA_CON_HORA.test(valor))
    );
}

/**
 * "2026-11-04" -> "04/11/2026". Con hora, solo la fecha en la hora local. Lo que no sea
 * una fecha se devuelve igual; nulo o vacío, el texto de `vacio`.
 */
export function formatearFecha(
    valor: string | null | undefined,
    vacio = '—',
): string {
    if (!valor) {
        return vacio;
    }

    const soloFecha = SOLO_FECHA.exec(valor);

    if (soloFecha) {
        return `${soloFecha[3]}/${soloFecha[2]}/${soloFecha[1]}`;
    }

    if (!FECHA_CON_HORA.test(valor)) {
        return valor;
    }

    const fecha = new Date(valor);

    return Number.isNaN(fecha.getTime())
        ? valor
        : `${dosDigitos(fecha.getDate())}/${dosDigitos(fecha.getMonth() + 1)}/${fecha.getFullYear()}`;
}

/** Para componentes que muestran un dato cualquiera: si es una fecha, en DD/MM/AAAA. */
export function mostrarFecha<T>(valor: T): T | string {
    return esFecha(valor) ? formatearFecha(valor) : valor;
}

/** "2026-11-04T15:30:00Z" -> "04/11/2026 10:30" (en la hora local). Sin hora, solo la fecha. */
export function formatearFechaHora(
    valor: string | null | undefined,
    vacio = '—',
): string {
    if (!valor) {
        return vacio;
    }

    if (SOLO_FECHA.test(valor) || !FECHA_CON_HORA.test(valor)) {
        return formatearFecha(valor, vacio);
    }

    const fecha = new Date(valor);

    if (Number.isNaN(fecha.getTime())) {
        return valor;
    }

    return `${formatearFecha(valor)} ${dosDigitos(fecha.getHours())}:${dosDigitos(fecha.getMinutes())}`;
}
