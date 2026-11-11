import { afterEach, describe, expect, it, vi } from 'vitest';
import { esFecha, formatearFecha, formatearFechaHora } from './fechas';

describe('formatearFecha', () => {
    it('muestra AAAA-MM-DD como DD/MM/AAAA', () => {
        expect(formatearFecha('2026-11-04')).toBe('04/11/2026');
        expect(formatearFecha('2026-11-04 00:00:00')).toBe('04/11/2026');
    });

    it('no pasa una fecha sin hora por Date, así la zona horaria no corre el día', () => {
        expect(formatearFecha('2026-01-01')).toBe('01/01/2026');
    });

    it('deja igual lo que no es una fecha y usa el texto de vacío si no hay', () => {
        expect(formatearFecha('Sin agendar')).toBe('Sin agendar');
        expect(formatearFecha(null)).toBe('—');
        expect(formatearFecha(undefined, 'Sin fecha')).toBe('Sin fecha');
    });
});

describe('formatearFechaHora', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('agrega la hora local a una fecha con hora', () => {
        const fecha = new Date(2026, 10, 4, 9, 5);

        expect(formatearFechaHora(fecha.toISOString())).toBe('04/11/2026 09:05');
    });

    it('sin hora, muestra solo la fecha', () => {
        expect(formatearFechaHora('2026-11-04')).toBe('04/11/2026');
    });
});

describe('esFecha', () => {
    it('reconoce fechas con y sin hora, y nada más', () => {
        expect(esFecha('2026-11-04')).toBe(true);
        expect(esFecha('2026-11-04T10:00:00Z')).toBe(true);
        expect(esFecha('EQ-2026-11')).toBe(false);
        expect(esFecha(20261104)).toBe(false);
    });
});
