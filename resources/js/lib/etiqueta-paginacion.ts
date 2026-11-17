const ENTIDADES: Record<string, string> = {
    '&laquo;': '«',
    '&raquo;': '»',
    '&amp;': '&',
    '&hellip;': '…',
};

/**
 * La etiqueta de un enlace del paginador de Laravel ("&laquo; Anterior") como texto, para
 * mostrarla como hijo del botón sin dangerouslySetInnerHTML.
 */
export function etiquetaPaginacion(etiqueta: string): string {
    return etiqueta.replace(
        /&(laquo|raquo|amp|hellip);/g,
        (entidad) => ENTIDADES[entidad],
    );
}
