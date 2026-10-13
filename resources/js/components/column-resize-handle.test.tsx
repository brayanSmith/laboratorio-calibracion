import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import { ColgroupAnchos } from '@/components/column-resize-handle';

describe('ColgroupAnchos', () => {
    it('no pinta nada mientras nadie haya redimensionado', () => {
        expect(
            renderToStaticMarkup(
                <ColgroupAnchos claves={['a', 'b']} anchos={null} />,
            ),
        ).toBe('');
    });

    it('fija el ancho de cada columna en el orden indicado', () => {
        const html = renderToStaticMarkup(
            <table>
                <ColgroupAnchos
                    claves={['codigo', 'modelo']}
                    anchos={{ modelo: 220, codigo: 90 }}
                />
            </table>,
        );

        expect(html).toContain(
            '<col style="width:90px"/><col style="width:220px"/>',
        );
    });
});
