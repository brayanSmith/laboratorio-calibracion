import type { CalibracionDetalleMedicion } from '@/types';

export type ResultadoMedicion = 'APROBADO' | 'NO_APROBADO';

/** Valor del instrumento escrito en cada fila de resultados, por id de detalle. */
export type ValoresInstrumento = Record<number, string>;

/**
 * Error encontrado = |valor de referencia - valor del instrumento| (siempre positivo). Null
 * si el valor del instrumento todavía no se llenó. Misma fórmula que
 * CalibracionController::guardarFormulario().
 */
export function calcularErrorEncontrado(
    valorReferencia: string,
    valorInstrumento: string,
): number | null {
    const instrumento = parseFloat(valorInstrumento);

    if (!Number.isFinite(instrumento)) {
        return null;
    }

    return Math.abs(parseFloat(valorReferencia) - instrumento);
}

/**
 * Resultado de una fila: APROBADO si el error encontrado no supera el EMP (el margen de
 * error permitido del equipo), NO_APROBADO si lo supera. Null si todavía no hay error
 * encontrado.
 */
export function calcularResultado(
    errorEncontrado: number | null,
    emp: string,
): ResultadoMedicion | null {
    if (errorEncontrado === null) {
        return null;
    }

    return errorEncontrado <= parseFloat(emp) ? 'APROBADO' : 'NO_APROBADO';
}

/**
 * Resultado de la calibración a partir de lo escrito en las filas (guardado o no):
 * NO_APROBADO si alguna fila lo es, APROBADO si todas están aprobadas (o no hay filas) y
 * null mientras falte completar alguna. Misma regla que CalibracionController::finalizar().
 */
export function resultadoDeLaCalibracion(
    detalles: CalibracionDetalleMedicion[],
    valores: ValoresInstrumento,
): ResultadoMedicion | null {
    const resultados = detalles.map((detalle) =>
        calcularResultado(
            calcularErrorEncontrado(
                detalle.valor_referencia,
                valores[detalle.id] ?? '',
            ),
            detalle.emp,
        ),
    );

    if (resultados.includes('NO_APROBADO')) {
        return 'NO_APROBADO';
    }

    return resultados.every((resultado) => resultado === 'APROBADO')
        ? 'APROBADO'
        : null;
}

/** Valores iniciales de las filas: lo ya guardado en cada detalle. */
export function valoresGuardados(
    detalles: CalibracionDetalleMedicion[],
): ValoresInstrumento {
    return Object.fromEntries(
        detalles.map((detalle) => [
            detalle.id,
            detalle.valor_instrumento ?? '',
        ]),
    );
}
