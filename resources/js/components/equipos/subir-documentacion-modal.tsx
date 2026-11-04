import { Form } from '@inertiajs/react';
import { Info } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { storePorModelo } from '@/actions/App/Modules/Equipos/Http/Controllers/DocumentoEquipoController';
import Combobox from '@/components/combobox';
import EquipoDocumentosFields from '@/components/equipos/equipo-documentos-fields';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { listasDocumentacionModelo } from '@/lib/documentacion-modelo';
import type { Equipo } from '@/types';

type Props = PropsWithChildren<{
    equipos: Equipo[];
}>;

const SIN_SELECCION = { tipoEquipoId: '', fabricanteId: '', modelo: '' };

/**
 * Sube una vez la documentación común a un tipo de equipo, marca y modelo (manuales,
 * certificados del fabricante...) y la deja en cada equipo que coincida. Los documentos
 * propios de cada equipo se siguen subiendo desde el equipo.
 */
export default function SubirDocumentacionModal({ equipos, children }: Props) {
    const [open, setOpen] = useState(false);
    const [seleccion, setSeleccion] = useState(SIN_SELECCION);
    const { tipos, marcas, modelos, coincidentes } = listasDocumentacionModelo(
        equipos,
        seleccion,
    );
    const completa =
        seleccion.tipoEquipoId !== '' &&
        seleccion.fabricanteId !== '' &&
        seleccion.modelo !== '';

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                setSeleccion(SIN_SELECCION);
            }}
        >
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <Form
                    key={String(open)}
                    {...storePorModelo.form()}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Subir documentación</DialogTitle>
                                <DialogDescription>
                                    Sube una sola vez los documentos que
                                    comparten todos los equipos de un mismo
                                    tipo, marca y modelo.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="documentacion-tipo-equipo">
                                        Tipo de equipo
                                    </Label>
                                    <Combobox
                                        id="documentacion-tipo-equipo"
                                        name="tipo_equipo_id"
                                        value={seleccion.tipoEquipoId}
                                        onValueChange={(tipoEquipoId) =>
                                            setSeleccion({
                                                ...SIN_SELECCION,
                                                tipoEquipoId,
                                            })
                                        }
                                        options={tipos}
                                        placeholder="Selecciona el tipo"
                                        searchPlaceholder="Buscar tipo..."
                                    />
                                    <InputError
                                        message={errors.tipo_equipo_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="documentacion-marca">
                                        Marca
                                    </Label>
                                    <Combobox
                                        id="documentacion-marca"
                                        name="fabricante_id"
                                        value={seleccion.fabricanteId}
                                        onValueChange={(fabricanteId) =>
                                            setSeleccion((actual) => ({
                                                ...actual,
                                                fabricanteId,
                                                modelo: '',
                                            }))
                                        }
                                        options={marcas}
                                        placeholder={
                                            seleccion.tipoEquipoId
                                                ? 'Selecciona la marca'
                                                : 'Primero el tipo'
                                        }
                                        searchPlaceholder="Buscar marca..."
                                        disabled={!seleccion.tipoEquipoId}
                                    />
                                    <InputError
                                        message={errors.fabricante_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="documentacion-modelo">
                                        Modelo
                                    </Label>
                                    <Combobox
                                        id="documentacion-modelo"
                                        name="modelo"
                                        value={seleccion.modelo}
                                        onValueChange={(modelo) =>
                                            setSeleccion((actual) => ({
                                                ...actual,
                                                modelo,
                                            }))
                                        }
                                        options={modelos}
                                        placeholder={
                                            seleccion.fabricanteId
                                                ? 'Selecciona el modelo'
                                                : 'Primero la marca'
                                        }
                                        searchPlaceholder="Buscar modelo..."
                                        disabled={!seleccion.fabricanteId}
                                    />
                                    <InputError message={errors.modelo} />
                                </div>
                            </div>

                            {completa ? (
                                <Alert data-test="documentacion-coincidentes">
                                    <Info />
                                    <AlertDescription>
                                        {coincidentes === 1
                                            ? 'Los documentos se agregarán a 1 equipo.'
                                            : `Los documentos se agregarán a ${coincidentes} equipos.`}{' '}
                                        Los documentos que cada equipo ya tiene
                                        se conservan.
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            <Separator />

                            <div className="space-y-3">
                                <div>
                                    <p className="text-sm font-medium">
                                        Documentos
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Un nombre y un archivo por cada
                                        documento.
                                    </p>
                                </div>
                                <EquipoDocumentosFields
                                    errors={errors}
                                    filasIniciales={1}
                                />
                                <InputError message={errors.documentos} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    loading={processing}
                                    disabled={!completa}
                                    data-test="documentacion-guardar"
                                >
                                    Guardar documentación
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
