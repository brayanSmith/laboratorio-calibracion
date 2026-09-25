/**
 * Turn pasted text into check list item names: one item per line, keeping each line as written.
 */
export function parseChecklistItems(text: string): string[] {
    return text
        .split(/\r\n|\r|\n/)
        .map((line) => line.trim())
        .filter((line) => line !== '');
}

const normalize = (name: string): string => name.trim().toLocaleLowerCase();

/**
 * Add the incoming names to the draft list, skipping the ones that are already in the
 * draft or already saved (case-insensitive). Reports how many were skipped.
 */
export function mergeChecklistItems(
    current: string[],
    incoming: string[],
    existing: string[],
): { items: string[]; skipped: number } {
    const seen = new Set([...current, ...existing].map(normalize));
    const items = [...current];
    let skipped = 0;

    for (const name of incoming) {
        const key = normalize(name);

        if (key === '' || seen.has(key)) {
            skipped++;
            continue;
        }

        seen.add(key);
        items.push(name.trim());
    }

    return { items, skipped };
}
