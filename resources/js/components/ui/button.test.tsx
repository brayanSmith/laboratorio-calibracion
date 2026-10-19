import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import { Button } from '@/components/ui/button';

describe('Button', () => {
    it('muestra el spinner y se deshabilita mientras está cargando', () => {
        const html = renderToStaticMarkup(
            <Button loading>Guardar</Button>,
        );

        expect(html).toContain('animate-spin');
        expect(html).toContain('disabled=""');
        expect(html).toContain('aria-busy="true"');
        expect(html).toContain('Guardar');
    });

    it('no muestra el spinner cuando no está cargando', () => {
        const html = renderToStaticMarkup(<Button>Guardar</Button>);

        expect(html).not.toContain('animate-spin');
        expect(html).not.toContain('disabled=""');
    });
});
