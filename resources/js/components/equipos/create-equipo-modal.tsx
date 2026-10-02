import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import EquipoController from '@/actions/App/Http/Controllers/EquipoController';
import EquipoDocumentosFields from '@/components/equipos/equipo-documentos-fields';
import EquipoEspecificacionTecnicaFields from '@/components/equipos/equipo-especificacion-tecnica-fields';
import EquipoFichaTecnicaFields from '@/components/equipos/equipo-ficha-tecnica-fields';
import EquipoFormFields from '@/components/equipos/equipo-form-fields';
import EquipoInformacionAdicionalFields from '@/components/equipos/equipo-informacion-adicional-fields';
import EquipoProgramacionesFields from '@/components/equipos/equipo-programaciones-fields';
import EquipoUbicacionFields from '@/components/equipos/equipo-ubicacion-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import type { EquipoFormOptions } from '@/types';

type Props = PropsWithChildren<{
    options: EquipoFormOptions;
}>;

export default function CreateEquipoModal({ options, children }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <Form
                    key={String(open)}
                    {...EquipoController.store.form()}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Nuevo equipo</DialogTitle>
                                <DialogDescription>
                                    Registra un nuevo equipo de metrología
                                </DialogDescription>
                            </DialogHeader>

                            <EquipoFormFields
                                options={options}
                                errors={errors}
                                variant="create"
                            />

                            <Separator />

                            <div className="space-y-4">
                                <Heading
                                    variant="small"
                                    title="Ficha técnica"
                                    description="Datos de adquisición del equipo"
                                />
                                <EquipoFichaTecnicaFields errors={errors} />
                            </div>

                            <Separator />

                            <div className="space-y-4">
                                <Heading
                                    variant="small"
                                    title="Ubicación"
                                    description="Dónde se encuentra físicamente el equipo"
                                />
                                <EquipoUbicacionFields
                                    options={options}
                                    errors={errors}
                                />
                            </div>

                            <Separator />

                            <div className="space-y-4">
                                <Heading
                                    variant="small"
                                    title="Especificación técnica"
                                    description="Opcional: puedes completarla ahora o después"
                                />
                                <EquipoEspecificacionTecnicaFields
                                    options={options}
                                    errors={errors}
                                />
                            </div>

                            <Separator />

                            <div className="space-y-4">
                                <Heading
                                    variant="small"
                                    title="Programación de servicio"
                                    description="Opcional: agrega una o varias (mantenimiento, calibración, etc.)"
                                />
                                <EquipoProgramacionesFields errors={errors} />
                            </div>

                            <Separator />

                            <div className="space-y-4">
                                <Heading
                                    variant="small"
                                    title="Información adicional"
                                    description="Notas y si es un patrón de referencia"
                                />
                                <EquipoInformacionAdicionalFields
                                    errors={errors}
                                />
                            </div>

                            <Separator />

                            <div className="space-y-4">
                                <Heading
                                    variant="small"
                                    title="Documentos"
                                    description="Opcional: adjunta manuales o fichas técnicas"
                                />
                                <EquipoDocumentosFields errors={errors} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="equipo-create-submit"
                                >
                                    Guardar equipo
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
