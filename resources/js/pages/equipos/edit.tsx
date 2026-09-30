import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import EquipoController from '@/actions/App/Http/Controllers/EquipoController';
import DeleteEquipoModal from '@/components/equipos/delete-equipo-modal';
import EquipoDocumentosSection from '@/components/equipos/equipo-documentos-section';
import EquipoEspecificacionTecnicaSection from '@/components/equipos/equipo-especificacion-tecnica-section';
import EquipoFichaTecnicaFields from '@/components/equipos/equipo-ficha-tecnica-fields';
import EquipoFormFields from '@/components/equipos/equipo-form-fields';
import EquipoInformacionAdicionalFields from '@/components/equipos/equipo-informacion-adicional-fields';
import EquipoProgramacionSection from '@/components/equipos/equipo-programacion-section';
import EquipoUbicacionFields from '@/components/equipos/equipo-ubicacion-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
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

                            <Separator />

                            <div className="space-y-4">
                                <Heading
                                    variant="small"
                                    title="Ficha técnica"
                                    description="Datos de adquisición del equipo"
                                />
                                <EquipoFichaTecnicaFields
                                    equipo={equipo}
                                    errors={errors}
                                />
                            </div>

                            <Separator />

                            <div className="space-y-4">
                                <Heading
                                    variant="small"
                                    title="Ubicación"
                                    description="Dónde se encuentra físicamente el equipo"
                                />
                                <EquipoUbicacionFields
                                    equipo={equipo}
                                    options={options}
                                    errors={errors}
                                />
                            </div>

                            <Separator />

                            <div className="space-y-4">
                                <Heading
                                    variant="small"
                                    title="Información adicional"
                                    description="Notas y si es un patrón de referencia"
                                />
                                <EquipoInformacionAdicionalFields
                                    equipo={equipo}
                                    errors={errors}
                                />
                            </div>

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

                <Separator />

                <EquipoEspecificacionTecnicaSection
                    equipo={equipo}
                    options={options}
                />

                <Separator />

                <EquipoProgramacionSection equipo={equipo} />

                <Separator />

                <EquipoDocumentosSection equipo={equipo} />
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
