import { describe, expect, it } from 'vitest';
import { etiquetaPaginacion } from './etiqueta-paginacion';

describe('etiquetaPaginacion', () => {
    it('decodes the arrows of the previous and next links', () => {
        expect(etiquetaPaginacion('&laquo; Anterior')).toBe('« Anterior');
        expect(etiquetaPaginacion('Siguiente &raquo;')).toBe('Siguiente »');
    });

    it('leaves page numbers untouched', () => {
        expect(etiquetaPaginacion('3')).toBe('3');
    });

    it('decodes the ellipsis between page numbers', () => {
        expect(etiquetaPaginacion('...')).toBe('...');
        expect(etiquetaPaginacion('&hellip;')).toBe('…');
    });
});
