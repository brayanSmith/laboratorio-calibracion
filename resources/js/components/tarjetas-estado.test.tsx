import { Clock } from 'lucide-react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import TarjetasEstado from '@/components/tarjetas-estado';
import type { TarjetaEstado } from '@/components/tarjetas-estado';

const pendientes: TarjetaEstado = {
    id: 'ingresos-pendientes',
    titulo: 'Pendientes',
    valor: 7,
    icono: Clock,
    tono: 'amber',
    detalle: 'Por recibir en el laboratorio',
    filtros: [{ id: 'estado_ingreso', value: 'PENDIENTE' }],
};

describe('TarjetasEstado', () => {
    it('muestra el conteo, el detalle y el botón para ver cada estado', () => {
        const html = renderToStaticMarkup(
            <TarjetasEstado
                tarjetas={[pendientes]}
                activa={null}
                onVer={() => undefined}
            />,
        );

        expect(html).toContain('data-test="tarjeta-ingresos-pendientes"');
        expect(html).toContain('>7<');
        expect(html).toContain('Por recibir en el laboratorio');
        expect(html).toContain('data-test="tarjeta-ingresos-pendientes-ver"');
        expect(html).not.toContain('ring-2');
    });

    it('resalta la tarjeta con la que se filtró la tabla', () => {
        const html = renderToStaticMarkup(
            <TarjetasEstado
                tarjetas={[pendientes]}
                activa="ingresos-pendientes"
                onVer={() => undefined}
            />,
        );

        expect(html).toContain('ring-2');
    });
});
