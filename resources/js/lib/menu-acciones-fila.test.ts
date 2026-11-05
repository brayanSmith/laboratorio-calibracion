import { describe, expect, it } from 'vitest';
import { posicionMenuFila } from './menu-acciones-fila';

const ventana = { ancho: 1000, alto: 800 };
const menu = { ancho: 200, alto: 40 };

describe('posicionMenuFila', () => {
    it('centers the menu just below the click', () => {
        expect(posicionMenuFila({ x: 500, y: 300 }, menu, ventana)).toEqual({
            left: 400,
            top: 308,
        });
    });

    it('stays inside the window near the left and right edges', () => {
        expect(posicionMenuFila({ x: 20, y: 300 }, menu, ventana).left).toBe(8);
        expect(posicionMenuFila({ x: 990, y: 300 }, menu, ventana).left).toBe(
            792,
        );
    });

    it('opens above the click when it does not fit below', () => {
        expect(posicionMenuFila({ x: 500, y: 780 }, menu, ventana).top).toBe(
            732,
        );
    });
});
