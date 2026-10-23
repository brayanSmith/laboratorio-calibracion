/** Respuesta de error de una petición (ver HttpResponse de Inertia). */
type RespuestaConError = {
    status: number;
};

/**
 * Explica al usuario, en una frase, por qué falló una petición que no fue un error de
 * validación (esos se muestran junto a cada campo).
 */
export function motivoErrorHttp(respuesta: RespuestaConError): string {
    switch (respuesta.status) {
        case 401:
        case 419:
            return 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.';
        case 403:
            return 'No tienes permiso para realizar esta acción.';
        case 404:
            return 'El recurso ya no existe. Recarga la página e inténtalo de nuevo.';
        case 413:
            return 'Los archivos superan el tamaño máximo que acepta el servidor.';
        case 429:
            return 'Demasiados intentos seguidos. Espera un momento e inténtalo de nuevo.';
        default:
            return respuesta.status >= 500
                ? `Ocurrió un error en el servidor (código ${respuesta.status}). Si se repite, avisa al administrador.`
                : `El servidor rechazó la petición (código ${respuesta.status}).`;
    }
}

/** Mensaje cuando no se pudo contactar al servidor. */
export const MOTIVO_SIN_CONEXION =
    'No se pudo conectar con el servidor. Revisa tu conexión a internet.';
