/**
 * Cambia el valor de un mes y lo copia a los meses siguientes que lo venían siguiendo:
 * los que estaban vacíos o tenían el mismo valor que ese mes antes del cambio. Se detiene
 * en el primer mes con un valor distinto, que es uno que el usuario ya ajustó.
 *
 * Así, escribir en enero llena todo el año, y cambiar julio ajusta de julio a diciembre.
 */
export function replicarHaciaAdelante(
    valores: string[],
    indice: number,
    nuevo: string,
): string[] {
    const anterior = valores[indice];
    const resultado = [...valores];

    resultado[indice] = nuevo;

    for (
        let siguiente = indice + 1;
        siguiente < resultado.length;
        siguiente++
    ) {
        const actual = resultado[siguiente];

        if (actual !== '' && actual !== anterior) {
            break;
        }

        resultado[siguiente] = nuevo;
    }

    return resultado;
}

/**
 * Si los meses no comparten un mismo objetivo (y por eso hay que mostrarlos uno a uno).
 * Todos vacíos, o todos con el mismo valor, no es personalizado.
 */
export function esPersonalizado(valores: string[]): boolean {
    return new Set(valores).size > 1;
}

/** Suma de los meses con valor; null si ninguno tiene. */
export function totalDelAnio(valores: string[]): number | null {
    const numeros = valores
        .filter((valor) => valor.trim() !== '')
        .map(Number)
        .filter((valor) => Number.isFinite(valor));

    return numeros.length === 0
        ? null
        : numeros.reduce((suma, valor) => suma + valor, 0);
}
