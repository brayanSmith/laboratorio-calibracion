import { describe, expect, it } from 'vitest';
import { mergeChecklistItems, parseChecklistItems } from './checklist-items';

describe('parseChecklistItems', () => {
    it('creates one item per line and ignores blank lines', () => {
        expect(
            parseChecklistItems(
                'Limpieza\n\n  Ajuste  \r\nEstado del vidrio\r',
            ),
        ).toEqual(['Limpieza', 'Ajuste', 'Estado del vidrio']);
    });

    it.each([
        '- Limpieza',
        '* Limpieza',
        '• Limpieza',
        '1. Limpieza',
        '2) Limpieza',
        '[ ] Limpieza',
        '1.5 mm de holgura',
    ])('keeps the text of "%s" exactly as written', (line) => {
        expect(parseChecklistItems(line)).toEqual([line]);
    });

    it('returns an empty list for whitespace only', () => {
        expect(parseChecklistItems(' \n\t\n')).toEqual([]);
    });
});

describe('mergeChecklistItems', () => {
    it('appends new names after the ones already in the draft', () => {
        expect(
            mergeChecklistItems(['Limpieza'], ['Ajuste', 'Calibración'], []),
        ).toEqual({
            items: ['Limpieza', 'Ajuste', 'Calibración'],
            skipped: 0,
        });
    });

    it('skips names already in the draft or already saved, ignoring case', () => {
        expect(
            mergeChecklistItems(
                ['Limpieza'],
                ['limpieza', 'AJUSTE', 'Nuevo'],
                ['Ajuste'],
            ),
        ).toEqual({ items: ['Limpieza', 'Nuevo'], skipped: 2 });
    });

    it('skips duplicates inside the incoming list itself', () => {
        expect(mergeChecklistItems([], ['Limpieza', 'limpieza'], [])).toEqual({
            items: ['Limpieza'],
            skipped: 1,
        });
    });
});
