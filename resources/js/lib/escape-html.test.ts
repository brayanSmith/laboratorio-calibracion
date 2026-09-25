import { describe, expect, it } from 'vitest';
import { escapeHtml } from './escape-html';

describe('escapeHtml', () => {
    it('leaves plain text untouched', () => {
        expect(escapeHtml('Metrología Andina S.A.S.')).toBe(
            'Metrología Andina S.A.S.',
        );
    });

    it('escapes the characters that can open or close tags and attributes', () => {
        expect(escapeHtml('</title><script>alert("x")</script>')).toBe(
            '&lt;/title&gt;&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;',
        );
        expect(escapeHtml("Laboratorio 'A' & Cía")).toBe(
            'Laboratorio &#039;A&#039; &amp; Cía',
        );
    });

    it('escapes an ampersand only once', () => {
        expect(escapeHtml('&lt;')).toBe('&amp;lt;');
    });
});
