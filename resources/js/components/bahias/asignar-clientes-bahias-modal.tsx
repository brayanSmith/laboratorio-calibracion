import type { PropsWithChildren } from 'react';
import { useMemo } from 'react';
import BahiaController from '@/actions/App/Modules/Catalogos/Http/Controllers/BahiaController';
import AsignarBahiasModal from '@/components/bahias/asignar-bahias-modal';
import type { Bahia, BahiaClienteOption } from '@/types';

type Props = PropsWithChildren<{
    bahias: Bahia[];
    clientes: BahiaClienteOption[];
}>;

/** Asigna clientes a las bahías (ver AsignarBahiasModal). */
export default function AsignarClientesBahiasModal({
    bahias,
    clientes,
    children,
}: Props) {
    const elementos = useMemo(
        () =>
            clientes.map((cliente) => ({
                id: cliente.id,
                titulo: cliente.nombre,
                detalle: cliente.numero_documento
                    ? `Documento ${cliente.numero_documento}`
                    : 'Sin documento',
            })),
        [clientes],
    );

    return (
        <AsignarBahiasModal
            bahias={bahias}
            elementos={elementos}
            asignadosDe={(bahia) => bahia.cliente_ids}
            nombre={{ singular: 'cliente', plural: 'clientes' }}
            buscarElemento="Buscar cliente o documento..."
            url={BahiaController.asignarClientes.url()}
            campoIds="cliente_ids"
            testId="asignar-clientes-bahias"
        >
            {children}
        </AsignarBahiasModal>
    );
}
