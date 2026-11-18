import { formDataToObject, objectToFormData } from '@inertiajs/core';
import { describe, expect, it } from 'vite-plus/test';

/**
 * Lo que hace Inertia al enviar un <Form> con archivos: lee los campos del formulario y los
 * vuelve a armar como FormData. Los nombres de las fotos deben sobrevivir con su índice.
 */
function enviarComoInertia(campos: [string, string | File][]): string[] {
    const formulario = new FormData();
    campos.forEach(([nombre, valor]) => formulario.append(nombre, valor));

    const enviado = objectToFormData(
        formDataToObject(formulario),
        new FormData(),
        null,
        'indices',
    );

    return Array.from(enviado.entries()).map(
        ([nombre, valor]) =>
            `${nombre}=${valor instanceof File ? `archivo:${valor.name}` : valor}`,
    );
}

const foto = (nombre: string) =>
    new File(['x'], nombre, { type: 'image/jpeg' });

describe('nombres de las fotos de recepcion', () => {
    it('cada foto llega bajo el id de su equipo, sin posiciones vacias', () => {
        expect(
            enviarComoInertia([
                ['equipos_recibidos[47][ingresado]', '1'],
                ['fotos_recepcion[47][0]', foto('frente.jpg')],
                ['fotos_recepcion[47][1]', foto('lado.jpg')],
            ]),
        ).toEqual([
            'equipos_recibidos[47][ingresado]=1',
            'fotos_recepcion[47][0]=archivo:frente.jpg',
            'fotos_recepcion[47][1]=archivo:lado.jpg',
        ]);
    });

    it('las fotos de un equipo no programado llegan junto a sus datos', () => {
        expect(
            enviarComoInertia([
                ['equipos_correctivos_recibidos[0][equipo_id]', '9'],
                ['equipos_correctivos_recibidos[0][falla_detectada]', 'Rota'],
                ['equipos_correctivos_recibidos[0][fotos][0]', foto('a.jpg')],
            ]),
        ).toEqual([
            'equipos_correctivos_recibidos[0][equipo_id]=9',
            'equipos_correctivos_recibidos[0][falla_detectada]=Rota',
            'equipos_correctivos_recibidos[0][fotos][0]=archivo:a.jpg',
        ]);
    });
});
