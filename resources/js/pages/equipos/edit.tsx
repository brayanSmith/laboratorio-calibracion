import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import EquipoController from '@/actions/App/Http/Controllers/EquipoController';
import DeleteEquipoModal from '@/components/equipos/delete-equipo-modal';
import EquipoFormFields from '@/components/equipos/equipo-form-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { edit, index } from '@/routes/equipos';
import type { Equipo, EquipoFormOptions } from '@/types';

type Props = {
    equipo: Equipo;
    options: EquipoFormOptions;
};

export default function EquiposEdit({ equipo, options }: Props) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    return (
        <>
            <Head title={`Editar equipo ${equipo.codigo}`} />

            <h1 className="sr-only">Editar equipo</h1>

            <div className="max-w-3xl space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={`Editar equipo ${equipo.codigo}`}
                        description="Actualiza la información del equipo"
                    />

                    <Button
                        variant="destructive"
                        size="sm"
                        data-test="equipo-delete-button"
                        onClick={() => setDeleteDialogOpen(true)}
                    >
                        Eliminar equipo
                    </Button>
                </div>

                <Form
                    {...EquipoController.update.form(equipo.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <EquipoFormFields
                                equipo={equipo}
                                options={options}
                                errors={errors}
                            />

                            <div className="flex items-center gap-4">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="equipo-update-button"
                                >
                                    Guardar cambios
                                </Button>
                                <Button variant="secondary" asChild>
                                    <Link href={index()}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteEquipoModal
                equipo={equipo}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />
        </>
    );
}

EquiposEdit.layout = (props: { equipo: Equipo }) => ({
    breadcrumbs: [
        { title: 'Equipos', href: index() },
        {
            title: `Editar ${props.equipo.codigo}`,
            href: edit(props.equipo.id),
        },
    ],
});
