/** Cómo se escribe y se muestra el valor de un objetivo (ver ConceptoObjetivo::formato). */
export type FormatoObjetivo = 'horas' | 'dias' | 'cantidad' | 'porcentaje';

/**
 * Horas guardadas (1.5) separadas en horas y minutos para los campos ({ horas: '1',
 * minutos: '30' }). Vacío si no hay valor.
 */
export function separarHoras(valor: string): {
    horas: string;
    minutos: string;
} {
    if (valor.trim() === '') {
        return { horas: '', minutos: '' };
    }

    const totalMinutos = Math.round(Number(valor) * 60);

    return {
        horas: String(Math.floor(totalMinutos / 60)),
        minutos: String(totalMinutos % 60),
    };
}

/**
 * Horas y minutos de los campos unidos en horas para guardar ('1' y '30' -> '1.5';
 * '' y '30' -> '0.5'). Vacío si los dos campos están vacíos.
 */
export function unirHoras(horas: string, minutos: string): string {
    if (horas.trim() === '' && minutos.trim() === '') {
        return '';
    }

    const total = (Number(horas) || 0) * 60 + (Number(minutos) || 0);

    return String(Math.round((total / 60) * 10000) / 10000);
}
