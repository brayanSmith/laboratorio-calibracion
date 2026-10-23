import { describe, expect, it } from 'vitest';
import { motivoErrorHttp } from './motivo-error-http';

describe('motivoErrorHttp', () => {
    it('explains the known statuses', () => {
        expect(motivoErrorHttp({ status: 403 })).toBe(
            'No tienes permiso para realizar esta acción.',
        );
        expect(motivoErrorHttp({ status: 419 })).toBe(
            'Tu sesión expiró. Recarga la página e inténtalo de nuevo.',
        );
        expect(motivoErrorHttp({ status: 413 })).toBe(
            'Los archivos superan el tamaño máximo que acepta el servidor.',
        );
    });

    it('includes the code of a server error', () => {
        expect(motivoErrorHttp({ status: 503 })).toBe(
            'Ocurrió un error en el servidor (código 503). Si se repite, avisa al administrador.',
        );
    });

    it('falls back to the code for any other status', () => {
        expect(motivoErrorHttp({ status: 409 })).toBe(
            'El servidor rechazó la petición (código 409).',
        );
    });
});
