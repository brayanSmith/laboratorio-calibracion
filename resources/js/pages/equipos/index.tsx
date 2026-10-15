import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import CreateEquipoModal from '@/components/equipos/create-equipo-modal';
import EquiposTabla from '@/components/equipos/equipos-tabla';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/equipos';
import type { Equipo, EquipoFormOptions } from '@/types';

type Props = {
    equipos: Equipo[];
    options: EquipoFormOptions;
};

export default function EquiposIndex({ equipos, options }: Props) {
    const { can } = usePermissions();

    return (
        <>
            <Head title="Equipos" />

            <h1 className="sr-only">Equipos</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Equipos"
                        description="Gestiona los equipos de metrología del laboratorio"
                    />

                    {can('equipos.crear') ? (
                        <CreateEquipoModal options={options}>
                            <Button data-test="equipos-new-button">
                                <Plus /> Nuevo equipo
                            </Button>
                        </CreateEquipoModal>
                    ) : null}
                </div>

                <EquiposTabla equipos={equipos} options={options} />
            </div>
        </>
    );
}

EquiposIndex.layout = {
    breadcrumbs: [
        {
            title: 'Equipos',
            href: index(),
        },
    ],
};
