import {
    diferenciaConObjetivo,
    objetivoMensualDelRango,
    sumaMensualDelRango,
} from '@/lib/periodos-grafico';
import type { DesempenoTecnicos } from '@/types';

type Responsable = DesempenoTecnicos['responsables'][number];
type ServicioDesempeno = 'MANTENIMIENTO' | 'CALIBRACION';

/** Cumplimiento de la cantidad: lo hecho frente al objetivo del periodo, en porcentaje. */
export type CumplimientoCantidad = { objetivo: number; porcentaje: number };

/**
 * Cumplimiento del tiempo: el tiempo frente al objetivo (cumple si no lo pasa). El
 * porcentaje es objetivo ÷ tiempo: 100 % o más cumple (3 h de objetivo en 2 h -> 150).
 */
export type CumplimientoTiempo = {
    objetivo: number;
    diferencia: number;
    cumple: boolean;
    porcentaje: number;
};

/** Cumplimiento de un tiempo frente a su objetivo; null si falta alguno de los dos. */
export function cumplimientoDeTiempo(
    horas: number | null,
    objetivo: number | null,
): CumplimientoTiempo | null {
    const diferencia = diferenciaConObjetivo(horas, objetivo, true);

    if (diferencia === null || horas === null || objetivo === null) {
        return null;
    }

    return {
        ...diferencia,
        objetivo,
        // Sin tiempo (0 h) no se puede dividir: cuenta como cumplido.
        porcentaje:
            horas <= 0 ? 100 : Math.round((objetivo / horas) * 1000) / 10,
    };
}

export type CumplimientoServicio = {
    cantidad: CumplimientoCantidad | null;
    tiempo: CumplimientoTiempo | null;
};

/** Qué parte del objetivo se hizo (12 de 15 -> 80); null sin objetivo (o si es cero). */
export function porcentajeDeCumplimiento(
    realizados: number,
    objetivo: number | null,
): CumplimientoCantidad | null {
    if (objetivo === null || objetivo <= 0) {
        return null;
    }

    return {
        objetivo,
        porcentaje: Math.round((realizados / objetivo) * 1000) / 10,
    };
}

const CLAVES = {
    MANTENIMIENTO: {
        resumen: 'mantenimientos',
        concepto: 'PROMEDIO_HORAS_MANTENIMIENTO',
    },
    CALIBRACION: {
        resumen: 'calibraciones',
        concepto: 'PROMEDIO_HORAS_CALIBRACION',
    },
} as const;

/**
 * Cumplimiento de un responsable en un servicio durante el rango [desde, hasta]:
 * - Cantidad: frente a la suma de sus objetivos por usuario de los meses del rango (de un
 *   mes cubierto en parte, la parte proporcional). Las empresas terceras no tienen.
 * - Tiempo: su promedio frente al tiempo objetivo de los tipos de equipo que atendió.
 */
export function cumplimientoDelResponsable(
    datos: DesempenoTecnicos,
    responsable: Responsable,
    servicio: ServicioDesempeno,
    desde: string,
    hasta: string,
): CumplimientoServicio {
    const { resumen, concepto } = CLAVES[servicio];
    const realizado = responsable[resumen];
    const suyos = datos.servicios.filter(
        (fila) =>
            fila.tipo_servicio === servicio &&
            fila.responsable_tipo === responsable.tipo &&
            fila.responsable_id === responsable.responsable_id,
    );

    const objetivoCantidad =
        responsable.tipo === 'TECNICO'
            ? sumaMensualDelRango(
                  datos.objetivos_usuarios.filter(
                      (objetivo) =>
                          objetivo.user_id === responsable.responsable_id &&
                          objetivo.tipo === servicio,
                  ),
                  desde,
                  hasta,
              )
            : null;
    const objetivoTiempo = objetivoMensualDelRango(
        datos.objetivos,
        suyos,
        desde,
        hasta,
        concepto,
    );
    return {
        cantidad: porcentajeDeCumplimiento(
            realizado.cantidad,
            objetivoCantidad,
        ),
        tiempo: cumplimientoDeTiempo(realizado.promedio_horas, objetivoTiempo),
    };
}

/**
 * Cumplimiento de tiempo de un solo servicio: sus horas frente al objetivo de su tipo de
 * equipo en su mes. Null sin tiempo registrado o sin objetivo.
 */
export function cumplimientoDelRegistro(
    datos: DesempenoTecnicos,
    registro: DesempenoTecnicos['servicios'][number],
): CumplimientoTiempo | null {
    const objetivo = objetivoMensualDelRango(
        datos.objetivos,
        [registro],
        registro.fecha,
        registro.fecha,
        CLAVES[registro.tipo_servicio].concepto,
    );

    return cumplimientoDeTiempo(registro.horas, objetivo);
}

/** Cumplimiento de todo lo filtrado en uno o varios servicios (las tarjetas del indicador). */
export type ResumenCumplimiento = {
    realizados: number;
    cantidad: CumplimientoCantidad | null;
    /** Suma de las horas registradas de los servicios. */
    horasTotales: number;
    promedioHoras: number | null;
    tiempo: CumplimientoTiempo | null;
};

/**
 * Cumplimiento de los servicios dados (mantenimiento, calibración o los dos) en el rango:
 * - Cantidad: lo hecho frente a la suma de los objetivos de los técnicos.
 * - Tiempo: el promedio de todos frente al objetivo de cada servicio, ponderado por
 *   cuántos servicios con tiempo tuvo cada uno.
 */
export function resumenDeCumplimiento(
    datos: DesempenoTecnicos,
    servicios: ServicioDesempeno[],
    desde: string,
    hasta: string,
): ResumenCumplimiento {
    let realizados = 0;
    let objetivoCantidad: number | null = null;
    let pesoTiempo = 0;
    let objetivoPonderado = 0;

    servicios.forEach((servicio) => {
        const { resumen, concepto } = CLAVES[servicio];
        const delServicio = datos.servicios.filter(
            (fila) => fila.tipo_servicio === servicio,
        );
        const cantidad = sumaMensualDelRango(
            datos.objetivos_usuarios.filter(
                (objetivo) => objetivo.tipo === servicio,
            ),
            desde,
            hasta,
        );
        const tiempo = objetivoMensualDelRango(
            datos.objetivos,
            delServicio,
            desde,
            hasta,
            concepto,
        );
        const conTiempo = delServicio.filter((fila) => fila.horas !== null);

        realizados += datos.totales[resumen].cantidad;

        if (cantidad !== null) {
            objetivoCantidad = (objetivoCantidad ?? 0) + cantidad;
        }

        if (tiempo !== null && conTiempo.length > 0) {
            pesoTiempo += conTiempo.length;
            objetivoPonderado += tiempo * conTiempo.length;
        }
    });

    const horas = datos.servicios
        .filter((fila) => servicios.includes(fila.tipo_servicio))
        .map((fila) => fila.horas)
        .filter((valor): valor is number => valor !== null);
    const horasTotales = horas.reduce((suma, valor) => suma + valor, 0);
    const promedioHoras = horas.length > 0 ? horasTotales / horas.length : null;
    const objetivoTiempo =
        pesoTiempo > 0 ? objetivoPonderado / pesoTiempo : null;

    return {
        realizados,
        cantidad: porcentajeDeCumplimiento(realizados, objetivoCantidad),
        horasTotales,
        promedioHoras,
        tiempo: cumplimientoDeTiempo(promedioHoras, objetivoTiempo),
    };
}
