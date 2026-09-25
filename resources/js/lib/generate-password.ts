const LOWER = 'abcdefghijkmnopqrstuvwxyz';
const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
const DIGITS = '23456789';
const SYMBOLS = '!@#$%*+-=?';

function randomIndex(max: number): number {
    const values = new Uint32Array(1);
    const limit = Math.floor(0x100000000 / max) * max;

    do {
        crypto.getRandomValues(values);
    } while (values[0] >= limit);

    return values[0] % max;
}

function pick(characters: string): string {
    return characters[randomIndex(characters.length)];
}

/**
 * Generate a random password containing lowercase, uppercase, digits and symbols.
 * Visually ambiguous characters (0/O, 1/l/I) are excluded so it can be dictated.
 */
export function generatePassword(length = 16): string {
    const all = LOWER + UPPER + DIGITS + SYMBOLS;
    const characters = [
        pick(LOWER),
        pick(UPPER),
        pick(DIGITS),
        pick(SYMBOLS),
        ...Array.from({ length: length - 4 }, () => pick(all)),
    ];

    for (let index = characters.length - 1; index > 0; index--) {
        const swap = randomIndex(index + 1);
        [characters[index], characters[swap]] = [
            characters[swap],
            characters[index],
        ];
    }

    return characters.join('');
}
