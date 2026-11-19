import type { PropsWithChildren } from 'react';
import { useMemo } from 'react';
import BahiaController from '@/actions/App/Modules/Catalogos/Http/Controllers/BahiaController';
import AsignarBahiasModal from '@/components/bahias/asignar-bahias-modal';
import type { Bahia, BahiaUsuarioOption } from '@/types';

type Props = PropsWithChildren<{
    bahias: Bahia[];
    usuarios: BahiaUsuarioOption[];
    /** Con qué lado se abre el modal (ej. "por usuario" desde Recordatorios). */
    modoInicial?: 'porBahia' | 'porUsuario';
}>;

/** Asigna usuarios a las bahías (ver AsignarBahiasModal). */
export default function AsignarUsuariosBahiasModal({
    bahias,
    usuarios,
    modoInicial = 'porBahia',
    children,
}: Props) {
    const elementos = useMemo(
        () =>
            usuarios.map((usuario) => ({
                id: usuario.id,
                titulo: usuario.name,
                detalle: usuario.email,
            })),
        [usuarios],
    );

    return (
        <AsignarBahiasModal
            bahias={bahias}
            elementos={elementos}
            asignadosDe={(bahia) => bahia.usuario_ids}
            nombre={{ singular: 'usuario', plural: 'usuarios' }}
            buscarElemento="Buscar usuario o correo..."
            url={BahiaController.asignarUsuarios.url()}
            campoIds="usuario_ids"
            testId="asignar-usuarios-bahias"
            modoInicial={
                modoInicial === 'porUsuario' ? 'porElemento' : 'porBahia'
            }
        >
            {children}
        </AsignarBahiasModal>
    );
}
