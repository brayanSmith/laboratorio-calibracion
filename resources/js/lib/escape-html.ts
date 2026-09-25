const ENTITIES: Record<string, string> = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
};

/**
 * Escape text so it can be placed inside HTML markup without being interpreted as tags.
 */
export function escapeHtml(text: string): string {
    return text.replace(/[&<>"']/g, (character) => ENTITIES[character]);
}
