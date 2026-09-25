import { describe, expect, it } from 'vitest';
import { fitWithin, normalizeRotation, rotatedBoundingBox } from './crop-image';

describe('rotatedBoundingBox', () => {
    it('keeps the size when the image is not rotated', () => {
        expect(rotatedBoundingBox(400, 200, 0)).toEqual({
            width: 400,
            height: 200,
        });
    });

    it('swaps width and height when rotated 90 degrees', () => {
        const box = rotatedBoundingBox(400, 200, 90);

        expect(box.width).toBeCloseTo(200);
        expect(box.height).toBeCloseTo(400);
    });

    it('grows to hold the corners when rotated 45 degrees', () => {
        const box = rotatedBoundingBox(100, 100, 45);

        expect(box.width).toBeCloseTo(141.42, 1);
        expect(box.height).toBeCloseTo(141.42, 1);
    });

    it('gives the same box for opposite rotations', () => {
        expect(rotatedBoundingBox(300, 100, -30)).toEqual(
            rotatedBoundingBox(300, 100, 30),
        );
    });
});

describe('fitWithin', () => {
    it('never enlarges an image that already fits', () => {
        expect(fitWithin(300, 200, 512)).toEqual({ width: 300, height: 200 });
    });

    it('scales the longest side down to the limit keeping the proportions', () => {
        expect(fitWithin(2000, 1000, 500)).toEqual({ width: 500, height: 250 });
        expect(fitWithin(1000, 4000, 400)).toEqual({ width: 100, height: 400 });
    });

    it('never returns a side smaller than one pixel', () => {
        expect(fitWithin(10000, 1, 100)).toEqual({ width: 100, height: 1 });
    });
});

describe('normalizeRotation', () => {
    it.each([
        [0, 0],
        [90, 90],
        [180, 180],
        [-180, 180],
        [270, -90],
        [-270, 90],
        [360, 0],
        [450, 90],
    ])('turns %i degrees into %i', (input, expected) => {
        expect(normalizeRotation(input)).toBe(expected);
    });
});
