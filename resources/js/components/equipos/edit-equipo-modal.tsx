import { Form } from '@inertiajs/react';
import { type PropsWithChildren, useState } from 'react';
import EquipoController from '@/actions/App/Http/Controllers/EquipoController';
import EquipoDocumentosEditarFields from '@/components/equipos/equipo-documentos-editar-fields';
import EquipoEspecificacionTecnicaFields from '@/components/equipos/equipo-especificacion-tecnica-fields';
import EquipoFichaTecnicaFields from '@/components/equipos/equipo-ficha-tecnica-fields';
import EquipoFormFields from '@/components/equipos/equipo-form-fields';
import EquipoInformacionAdicionalFields from '@/components/equipos/equipo-informacion-adicional-fields';
import EquipoProgramacionesEditarFields from '@/components/equipos/equipo-programaciones-editar-fields';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { Equipo, EquipoFormOptions } from '@/types';

type Props = PropsWithChildren<{
    equipo: Equipo;
    options: EquipoFormOptions;
}>;

export default function EditEquipoModal({ equipo, options, children }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <Form
                    key={`${equipo.id}-${String(open)}`}
                    {...EquipoController.update.form(equipo.id)}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Editar equipo {equipo.codigo}
                                </DialogTitle>
                                <DialogDescription>
                                    Actualiza la información del equipo
                                </DialogDescription>
                            </DialogHeader>

                            <Tabs defaultValue="general">
                                <TabsList className="grid w-full grid-cols-2">
                                    <TabsTrigger type="button" value="general">
                                        General
                                    </TabsTrigger>
                                    <TabsTrigger
                                        type="button"
                                        value="programaciones"
                                    >
                                        Programaciones
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent
                                    value="general"
                                    forceMount
                                    className="space-y-6 data-[state=inactive]:hidden"
                                >
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

                                    <Separator />

                                    <div className="space-y-4">
                                        <Heading
                                            variant="small"
                                            title="Especificación técnica"
                                            description="Opcional: magnitud, unidad y alcance que mide este equipo"
                                        />
                                        <EquipoEspecificacionTecnicaFields
                                            especificacion={
                                                equipo.equipo_especificacion_tecnica
                                            }
                                            options={options}
                                            errors={errors}
                                        />
                                    </div>

                                    <Separator />

                                    <div className="space-y-4">
                                        <Heading
                                            variant="small"
                                            title="Documentos"
                                            description="Marca los que quieras eliminar o agrega nuevos"
                                        />
                                        <EquipoDocumentosEditarFields
                                            documentos={
                                                equipo.equipo_documentos ?? []
                                            }
                                            errors={errors}
                                        />
                                    </div>
                                </TabsContent>

                                <TabsContent
                                    value="programaciones"
                                    forceMount
                                    className="data-[state=inactive]:hidden"
                                >
                                    <div className="space-y-4">
                                        <Heading
                                            variant="small"
                                            title="Programación de servicio"
                                            description="Marca las que quieras eliminar o agrega nuevas"
                                        />
                                        <EquipoProgramacionesEditarFields
                                            programaciones={
                                                equipo.equipo_programaciones ??
                                                []
                                            }
                                            errors={errors}
                                        />
                                    </div>
                                </TabsContent>
                            </Tabs>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="equipo-update-button"
                                >
                                    Guardar cambios
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
