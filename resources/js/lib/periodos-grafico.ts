import type { ConceptoObjetivo, ObjetivosDelPeriodo } from '@/types';

/** Cómo se agrupan los datos del gráfico en el eje de tiempo. */
export type Agrupacion =
    'dia' | 'semana' | 'mes' | 'trimestre' | 'semestre' | 'anio';

export const AGRUPACIONES: { value: Agrupacion; label: string }[] = [
    { value: 'dia', label: 'Días' },
    { value: 'semana', label: 'Semanas' },
    { value: 'mes', label: 'Meses' },
    { value: 'trimestre', label: 'Trimestres' },
    { value: 'semestre', label: 'Semestres' },
    { value: 'anio', label: 'Años' },
];

/** Meses que dura cada agrupación de meses. */
const MESES_POR_PERIODO = { mes: 1, trimestre: 3, semestre: 6, anio: 12 };

const MESES = [
    'ene',
    'feb',
    'mar',
    'abr',
    'may',
    'jun',
    'jul',
    'ago',
    'sep',
    'oct',
    'nov',
    'dic',
];

const DIA_MS = 24 * 60 * 60 * 1000;

/** "2026-03-05" como fecha UTC (sin que la zona horaria corra el día). */
function aFecha(texto: string): Date {
    const [anio, mes, dia] = texto.slice(0, 10).split('-').map(Number);

    return new Date(Date.UTC(anio, mes - 1, dia));
}

/** Fecha UTC como "2026-03-05". */
function aTexto(fecha: Date): string {
    return fecha.toISOString().slice(0, 10);
}

/** Primer día del periodo (de la agrupación) al que pertenece la fecha. */
export function inicioDePeriodo(fecha: string, agrupacion: Agrupacion): string {
    const dia = aFecha(fecha);

    if (agrupacion === 'dia') {
        return aTexto(dia);
    }

    if (agrupacion === 'semana') {
        // Semanas de lunes a domingo.
        const desdeLunes = (dia.getUTCDay() + 6) % 7;

        return aTexto(new Date(dia.getTime() - desdeLunes * DIA_MS));
    }

    const meses = MESES_POR_PERIODO[agrupacion];
    const mes = Math.floor(dia.getUTCMonth() / meses) * meses;

    return aTexto(new Date(Date.UTC(dia.getUTCFullYear(), mes, 1)));
}

/** Inicio del periodo siguiente. */
function siguiente(inicio: string, agrupacion: Agrupacion): string {
    const fecha = aFecha(inicio);

    if (agrupacion === 'dia' || agrupacion === 'semana') {
        const dias = agrupacion === 'dia' ? 1 : 7;

        return aTexto(new Date(fecha.getTime() + dias * DIA_MS));
    }

    return aTexto(
        new Date(
            Date.UTC(
                fecha.getUTCFullYear(),
                fecha.getUTCMonth() + MESES_POR_PERIODO[agrupacion],
                1,
            ),
        ),
    );
}

/**
 * Nombre corto del periodo en el eje: "5 mar", "sem 2 mar", "mar 2026", "T1 2026",
 * "S2 2026", "2026".
 */
export function etiquetaPeriodo(
    inicio: string,
    agrupacion: Agrupacion,
): string {
    const fecha = aFecha(inicio);
    const anio = fecha.getUTCFullYear();
    const mes = fecha.getUTCMonth();
    const diaMes = `${fecha.getUTCDate()} ${MESES[mes]}`;

    switch (agrupacion) {
        case 'dia':
            return diaMes;
        case 'semana':
            return `sem ${diaMes}`;
        case 'mes':
            return `${MESES[mes]} ${anio}`;
        case 'trimestre':
            return `T${Math.floor(mes / 3) + 1} ${anio}`;
        case 'semestre':
            return `S${mes < 6 ? 1 : 2} ${anio}`;
        case 'anio':
            return String(anio);
    }
}

/**
 * Días que debe durar el periodo para que una agrupación tenga sentido: no se agrupa por
 * meses una semana, ni por años un trimestre.
 */
const DIAS_MINIMOS: Record<Agrupacion, number> = {
    dia: 1,
    semana: 7,
    mes: 28,
    trimestre: 90,
    semestre: 181,
    anio: 365,
};

/** Días que dura el periodo, contando el primero y el último. */
function diasDelPeriodo(desde: string, hasta: string): number {
    return (aFecha(hasta).getTime() - aFecha(desde).getTime()) / DIA_MS + 1;
}

/** Si la agrupación tiene sentido para el periodo (no es más larga que el periodo). */
export function agrupacionDisponible(
    agrupacion: Agrupacion,
    desde: string,
    hasta: string,
): boolean {
    return diasDelPeriodo(desde, hasta) >= DIAS_MINIMOS[agrupacion];
}

/** Agrupación con la que abre el gráfico según cuánto dura el periodo. */
export function agrupacionSugerida(desde: string, hasta: string): Agrupacion {
    const dias = diasDelPeriodo(desde, hasta);

    if (dias <= 14) {
        return 'dia';
    }

    if (dias <= 92) {
        return 'semana';
    }

    return dias <= 731 ? 'mes' : 'trimestre';
}

export type PuntoGrafico = {
    /** Primer día del periodo (AAAA-MM-DD). */
    inicio: string;
    etiqueta: string;
    /** Promedio de los valores del periodo; null si ninguno tuvo valor. */
    valor: number | null;
    /** Cuántas filas cayeron en el periodo (con o sin valor). */
    cantidad: number;
};

/**
 * Un punto por cada periodo entre `desde` y `hasta` (aunque no tenga filas, para que el eje
 * no salte) con el promedio de los valores de las filas de ese periodo.
 */
export function agruparPromedios(
    filas: { fecha: string; valor: number | null }[],
    desde: string,
    hasta: string,
    agrupacion: Agrupacion,
): PuntoGrafico[] {
    const porPeriodo = new Map<
        string,
        { suma: number; conValor: number; cantidad: number }
    >();

    filas.forEach(({ fecha, valor }) => {
        const inicio = inicioDePeriodo(fecha, agrupacion);
        const acumulado = porPeriodo.get(inicio) ?? {
            suma: 0,
            conValor: 0,
            cantidad: 0,
        };

        acumulado.cantidad += 1;

        if (valor !== null) {
            acumulado.suma += valor;
            acumulado.conValor += 1;
        }

        porPeriodo.set(inicio, acumulado);
    });

    const puntos: PuntoGrafico[] = [];
    const ultimo = inicioDePeriodo(hasta, agrupacion);

    for (
        let inicio = inicioDePeriodo(desde, agrupacion);
        inicio <= ultimo;
        inicio = siguiente(inicio, agrupacion)
    ) {
        const acumulado = porPeriodo.get(inicio);

        puntos.push({
            inicio,
            etiqueta: etiquetaPeriodo(inicio, agrupacion),
            valor:
                acumulado && acumulado.conValor > 0
                    ? Math.round((acumulado.suma / acumulado.conValor) * 10) /
                      10
                    : null,
            cantidad: acumulado?.cantidad ?? 0,
        });
    }

    return puntos;
}

/**
 * Si en el objetivo es mejor quedar por debajo (tiempos) o por encima (cantidades y
 * cumplimiento): decide si la diferencia se ve en verde o en rojo.
 */
export function menorEsMejor(concepto: ConceptoObjetivo): boolean {
    return (
        concepto === 'TIEMPO_ATENCION_DIAS' ||
        concepto === 'PROMEDIO_HORAS_MANTENIMIENTO' ||
        concepto === 'PROMEDIO_HORAS_CALIBRACION'
    );
}

/**
 * Objetivo anual de un periodo: el del año en que empieza (así, si el gráfico abarca dos
 * años, cada uno muestra el suyo). Los objetivos por mes van en objetivoMensualDelPeriodo.
 */
export function objetivoDelPeriodo(
    objetivos: ObjetivosDelPeriodo,
    inicio: string,
    concepto: ConceptoObjetivo,
): number | null {
    const anio = Number(inicio.slice(0, 4));
    const valor = objetivos.find((objetivo) => objetivo.anio === anio)?.valores[
        concepto
    ];

    if (valor === undefined || valor === null) {
        return null;
    }

    return valor;
}

/** Colores de la línea del valor: verde si cumple el objetivo, rojo si no. */
export const COLOR_CUMPLE = '#16a34a';
export const COLOR_NO_CUMPLE = '#dc2626';

type PuntoConObjetivo = { valor: number | null; objetivo: number | null };

/** Si el punto cumple su objetivo (sin objetivo, cuenta como que cumple); null sin valor. */
export function cumpleObjetivo(
    punto: PuntoConObjetivo,
    menorMejor: boolean,
): boolean | null {
    if (punto.valor === null) {
        return null;
    }

    if (punto.objetivo === null) {
        return true;
    }

    return menorMejor
        ? punto.valor <= punto.objetivo
        : punto.valor >= punto.objetivo;
}

/**
 * Cortes de color de la línea del valor, de izquierda a derecha (0 a 1, del primer al
 * último punto con valor): verde donde cumple y rojo donde no. Entre dos puntos seguidos
 * con distinto resultado, el color cambia donde la línea cruza el objetivo.
 */
export function cortesDeColor(
    puntos: PuntoConObjetivo[],
    menorMejor: boolean,
): { offset: number; color: string }[] {
    const colorDe = (indice: number) =>
        cumpleObjetivo(puntos[indice], menorMejor)
            ? COLOR_CUMPLE
            : COLOR_NO_CUMPLE;
    const conValor = puntos
        .map((punto, indice) => (punto.valor === null ? null : indice))
        .filter((indice): indice is number => indice !== null);

    if (conValor.length === 0) {
        return [];
    }

    const primero = conValor[0];
    const largo = conValor[conValor.length - 1] - primero;
    const cortes = [{ offset: 0, color: colorDe(primero) }];

    for (let i = 1; i < conValor.length; i++) {
        const a = conValor[i - 1];
        const b = conValor[i];

        if (colorDe(a) === colorDe(b)) {
            continue;
        }

        // Dónde la línea cruza el objetivo; sin objetivo en alguno de los dos, a la mitad.
        let t = 0.5;
        const [pa, pb] = [puntos[a], puntos[b]];

        if (b === a + 1 && pa.objetivo !== null && pb.objetivo !== null) {
            const da = (pa.valor as number) - pa.objetivo;
            const db = (pb.valor as number) - pb.objetivo;

            if (da !== db) {
                t = Math.min(1, Math.max(0, da / (da - db)));
            }
        }

        const offset = largo === 0 ? 0 : (a + t * (b - a) - primero) / largo;
        cortes.push(
            { offset, color: colorDe(a) },
            { offset, color: colorDe(b) },
        );
    }

    cortes.push({ offset: 1, color: colorDe(conValor[conValor.length - 1]) });

    return cortes;
}

/**
 * Diferencia entre el valor y el objetivo (redondeada a un decimal) y si cumple; null si
 * falta alguno de los dos.
 */
export function diferenciaConObjetivo(
    valor: number | null,
    objetivo: number | null,
    menorMejor: boolean,
): { diferencia: number; cumple: boolean } | null {
    if (valor === null || objetivo === null) {
        return null;
    }

    const diferencia = Math.round((valor - objetivo) * 10) / 10;

    return {
        diferencia,
        cumple: menorMejor ? diferencia <= 0 : diferencia >= 0,
    };
}

/**
 * Variación frente al periodo anterior: la diferencia (redondeada a un decimal), su
 * porcentaje (null si el anterior es 0) y si mejora; null si falta alguno de los dos
 * valores. Sin cambio no mejora ni empeora (mejora: null).
 */
export function variacionConAnterior(
    valor: number | null,
    anterior: number | null,
    menorMejor: boolean,
): {
    diferencia: number;
    porcentaje: number | null;
    mejora: boolean | null;
} | null {
    if (valor === null || anterior === null) {
        return null;
    }

    const diferencia = Math.round((valor - anterior) * 10) / 10;

    return {
        diferencia,
        porcentaje:
            anterior === 0
                ? null
                : Math.round(((valor - anterior) / anterior) * 1000) / 10,
        mejora:
            diferencia === 0
                ? null
                : menorMejor
                  ? diferencia < 0
                  : diferencia > 0,
    };
}

/**
 * Techo "redondo" del eje vertical (desde 0) para que quepa el máximo, con sus marcas: unas
 * cuatro, de pasos de 1, 2, 2.5 o 5 por una potencia de 10 (sin el 2.5 si son enteros).
 */
export function techoDelEje(
    maximo: number,
    enteros: boolean,
): { techo: number; marcas: number[] } {
    const bruto = Math.max(maximo, enteros ? 1 : 0.1) / 4;
    const magnitud = 10 ** Math.floor(Math.log10(bruto));
    const factores = enteros ? [1, 2, 5, 10] : [1, 2, 2.5, 5, 10];
    const factor =
        factores.find((posible) => posible * magnitud >= bruto) ?? 10;
    const paso = enteros
        ? Math.max(1, Math.ceil(factor * magnitud))
        : factor * magnitud;
    const pasos = Math.max(1, Math.ceil(maximo / paso - 1e-9));
    const redondear = (valor: number) => Math.round(valor * 1e6) / 1e6;

    return {
        techo: redondear(pasos * paso),
        marcas: Array.from({ length: pasos + 1 }, (_, indice) =>
            redondear(indice * paso),
        ),
    };
}

/** Objetivo de un mes y un tipo de equipo (desempeño por técnico). */
export type ObjetivoMensual = {
    anio: number;
    mes: number;
    tipo_equipo_id: number;
    valores: Partial<Record<ConceptoObjetivo, number>>;
};

/** Un mes que toca un rango: sus límites dentro del rango y qué parte de él cubre. */
type MesDelRango = {
    anio: number;
    mes: number;
    desde: number;
    hasta: number;
    /** Parte del mes que cubre el rango (1 si lo cubre completo). */
    parte: number;
};

/** Los meses que toca el rango [desde, hasta) (fechas YYYY-MM-DD), con la parte de cada uno. */
function mesesDelRango(desde: string, hastaSinIncluir: string): MesDelRango[] {
    const inicio = aFecha(desde).getTime();
    const fin = aFecha(hastaSinIncluir).getTime();
    const meses: MesDelRango[] = [];

    for (
        let mes = aFecha(inicioDePeriodo(desde, 'mes')).getTime();
        mes < fin;
        mes = aFecha(siguiente(aTexto(new Date(mes)), 'mes')).getTime()
    ) {
        const finMes = aFecha(
            siguiente(aTexto(new Date(mes)), 'mes'),
        ).getTime();
        const desdeEnMes = Math.max(inicio, mes);
        const hastaEnMes = Math.min(fin, finMes);
        const fecha = new Date(mes);

        meses.push({
            anio: fecha.getUTCFullYear(),
            mes: fecha.getUTCMonth() + 1,
            desde: desdeEnMes,
            hasta: hastaEnMes,
            parte: (hastaEnMes - desdeEnMes) / (finMes - mes),
        });
    }

    return meses;
}

/** Los meses del periodo que empieza en `inicio` (un día, una semana, un mes...). */
const mesesDelPeriodo = (inicio: string, agrupacion: Agrupacion) =>
    mesesDelRango(inicio, siguiente(inicio, agrupacion));

/** Los meses del rango de días [desde, hasta], ambos incluidos (el filtro del indicador). */
const mesesDeLosDias = (desde: string, hasta: string) =>
    mesesDelRango(desde, siguiente(hasta, 'dia'));

/** Promedio de los tiempos objetivo de los meses dados (ver objetivoMensualDelPeriodo). */
function tiempoObjetivoDeMeses(
    meses: MesDelRango[],
    objetivos: ObjetivoMensual[],
    filas: { fecha: string; tipo_equipo_id: number | null }[],
    concepto: ConceptoObjetivo,
): number | null {
    const items = meses.flatMap((mes) =>
        objetivos
            .filter(
                (objetivo) =>
                    objetivo.anio === mes.anio &&
                    objetivo.mes === mes.mes &&
                    objetivo.valores[concepto] !== undefined,
            )
            .map((objetivo) => ({
                valor: objetivo.valores[concepto] as number,
                dias: mes.parte,
                servicios: filas.filter((fila) => {
                    const dia = aFecha(fila.fecha).getTime();

                    return (
                        fila.tipo_equipo_id === objetivo.tipo_equipo_id &&
                        dia >= mes.desde &&
                        dia < mes.hasta
                    );
                }).length,
            })),
    );

    if (items.length === 0) {
        return null;
    }

    const conServicios = items.reduce((suma, item) => suma + item.servicios, 0);
    const peso = (item: (typeof items)[number]) =>
        conServicios > 0 ? item.servicios : item.dias;
    const pesoTotal = items.reduce((suma, item) => suma + peso(item), 0);

    return pesoTotal === 0
        ? null
        : items.reduce((suma, item) => suma + item.valor * peso(item), 0) /
              pesoTotal;
}

/** Suma de las cantidades objetivo de los meses dados (ver sumaMensualDelPeriodo). */
function cantidadObjetivoDeMeses(
    meses: MesDelRango[],
    objetivos: { anio: number; mes: number; valor: number }[],
): number | null {
    let total: number | null = null;

    meses.forEach((mes) => {
        objetivos
            .filter(
                (objetivo) =>
                    objetivo.anio === mes.anio && objetivo.mes === mes.mes,
            )
            .forEach((objetivo) => {
                total = (total ?? 0) + objetivo.valor * mes.parte;
            });
    });

    return total;
}

/**
 * Tiempo objetivo de un periodo a partir de los objetivos por mes y tipo de equipo,
 * combinando los tipos: promedio de los objetivos, ponderado por cuántos servicios tuvo
 * cada tipo en el periodo; sin servicios, ponderado por los días que el periodo cubre de
 * cada mes.
 *
 * Null si ningún mes del periodo tiene objetivo para el concepto.
 */
export function objetivoMensualDelPeriodo(
    objetivos: ObjetivoMensual[],
    filas: { fecha: string; tipo_equipo_id: number | null }[],
    inicio: string,
    agrupacion: Agrupacion,
    concepto: ConceptoObjetivo,
): number | null {
    return tiempoObjetivoDeMeses(
        mesesDelPeriodo(inicio, agrupacion),
        objetivos,
        filas,
        concepto,
    );
}

/** Como objetivoMensualDelPeriodo, para el rango de días [desde, hasta]. */
export function objetivoMensualDelRango(
    objetivos: ObjetivoMensual[],
    filas: { fecha: string; tipo_equipo_id: number | null }[],
    desde: string,
    hasta: string,
    concepto: ConceptoObjetivo,
): number | null {
    return tiempoObjetivoDeMeses(
        mesesDeLosDias(desde, hasta),
        objetivos,
        filas,
        concepto,
    );
}

/**
 * Cantidad objetivo de un periodo a partir de objetivos por mes (los de los usuarios): se
 * suman los meses del periodo y, de un mes que cubre solo en parte (días, semanas), se
 * toma la parte proporcional a sus días.
 *
 * Null si ningún mes del periodo tiene objetivo.
 */
export function sumaMensualDelPeriodo(
    objetivos: { anio: number; mes: number; valor: number }[],
    inicio: string,
    agrupacion: Agrupacion,
): number | null {
    return cantidadObjetivoDeMeses(
        mesesDelPeriodo(inicio, agrupacion),
        objetivos,
    );
}

/** Como sumaMensualDelPeriodo, para el rango de días [desde, hasta]. */
export function sumaMensualDelRango(
    objetivos: { anio: number; mes: number; valor: number }[],
    desde: string,
    hasta: string,
): number | null {
    return cantidadObjetivoDeMeses(mesesDeLosDias(desde, hasta), objetivos);
}

/** Un periodo del gráfico de cumplimiento: programados, atendidos y su porcentaje. */
export type PuntoCumplimiento = {
    inicio: string;
    etiqueta: string;
    programados: number;
    atendidos: number;
    /** Null sin equipos programados en el periodo. */
    porcentaje: number | null;
};

/**
 * Un punto por cada periodo entre `desde` y `hasta` (aunque no tenga equipos) con los
 * equipos programados en él, cuántos se atendieron y el porcentaje.
 */
export function agruparCumplimiento(
    equipos: { fecha: string; atendido: boolean }[],
    desde: string,
    hasta: string,
    agrupacion: Agrupacion,
): PuntoCumplimiento[] {
    const programados = agruparPromedios(
        equipos.map(({ fecha }) => ({ fecha, valor: null })),
        desde,
        hasta,
        agrupacion,
    );
    const atendidos = agruparPromedios(
        equipos
            .filter(({ atendido }) => atendido)
            .map(({ fecha }) => ({ fecha, valor: null })),
        desde,
        hasta,
        agrupacion,
    );

    return programados.map((punto, indice) => {
        const delPeriodo = atendidos[indice].cantidad;

        return {
            inicio: punto.inicio,
            etiqueta: punto.etiqueta,
            programados: punto.cantidad,
            atendidos: delPeriodo,
            porcentaje:
                punto.cantidad === 0
                    ? null
                    : Math.round((delPeriodo * 1000) / punto.cantidad) / 10,
        };
    });
}
