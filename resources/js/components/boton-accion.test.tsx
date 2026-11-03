import { Pencil, Printer } from 'lucide-react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import BotonAccion from '@/components/boton-accion';
import { TooltipProvider } from '@/components/ui/tooltip';

describe('BotonAccion', () => {
    it('muestra solo el icono y lleva su nombre como nombre accesible', () => {
        const html = renderToStaticMarkup(
            <TooltipProvider>
                <BotonAccion icono={Pencil} etiqueta="Editar" />
            </TooltipProvider>,
        );

        expect(html).toContain('<button');
        expect(html).toContain('aria-label="Editar"');
        // El globo con el nombre solo se monta al pasar el cursor.
        expect(html).not.toContain('>Editar<');
    });

    it('con href es un enlace que abre en otra pestaña de forma segura', () => {
        const html = renderToStaticMarkup(
            <TooltipProvider>
                <BotonAccion
                    icono={Printer}
                    etiqueta="Informe PDF"
                    href="/informe/1"
                    target="_blank"
                />
            </TooltipProvider>,
        );

        expect(html).toContain('<a');
        expect(html).toContain('href="/informe/1"');
        expect(html).toContain('rel="noreferrer"');
        expect(html).toContain('aria-label="Informe PDF"');
    });
});
