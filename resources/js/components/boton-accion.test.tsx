import { Pencil, Printer } from 'lucide-react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import BotonAccion from '@/components/boton-accion';

describe('BotonAccion', () => {
    it('lleva su nombre como nombre accesible y lo oculta hasta pasar el cursor', () => {
        const html = renderToStaticMarkup(
            <BotonAccion icono={Pencil} etiqueta="Editar" />,
        );

        expect(html).toContain('<button');
        expect(html).toContain('aria-label="Editar"');
        expect(html).toContain('max-w-0');
        expect(html).toContain('group-hover/accion:max-w-40');
    });

    it('con href es un enlace que abre en otra pestaña de forma segura', () => {
        const html = renderToStaticMarkup(
            <BotonAccion
                icono={Printer}
                etiqueta="Informe PDF"
                href="/informe/1"
                target="_blank"
            />,
        );

        expect(html).toContain('<a');
        expect(html).toContain('href="/informe/1"');
        expect(html).toContain('rel="noreferrer"');
        expect(html).toContain('aria-label="Informe PDF"');
    });
});
