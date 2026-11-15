import { Form } from '@inertiajs/react';
import { useState } from 'react';
import RecordatorioPlantillaController from '@/actions/App/Modules/Recordatorios/Http/Controllers/RecordatorioPlantillaController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    PlantillaPersonalizada,
    TipoPlantillaRecordatorio,
} from '@/types';

/**
 * Pide el nombre de una plantilla nueva (ej. "Navidad"). Se crea con los textos de la
 * predeterminada, para solo ajustarlos.
 */
export function NuevaPlantillaDialog({
    tipo,
    tituloCorreo,
    open,
    onOpenChange,
    onCreada,
}: {
    tipo: TipoPlantillaRecordatorio;
    tituloCorreo: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreada: (nombre: string) => void;
}) {
    const [nombre, setNombre] = useState('');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...RecordatorioPlantillaController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        onCreada(nombre.trim());
                        onOpenChange(false);
                        setNombre('');
                    }}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Nueva plantilla</DialogTitle>
                                <DialogDescription>
                                    Para el {tituloCorreo.toLowerCase()}.
                                    Empieza con los textos de la predeterminada;
                                    luego los ajustas y la pones en uso cuando
                                    la necesites (ej. en Navidad).
                                </DialogDescription>
                            </DialogHeader>

                            <input type="hidden" name="tipo" value={tipo} />

                            <div className="grid gap-2">
                                <Label htmlFor={`nueva_plantilla_${tipo}`}>
                                    Nombre
                                </Label>
                                <Input
                                    id={`nueva_plantilla_${tipo}`}
                                    name="nombre"
                                    maxLength={80}
                                    placeholder="Ej. Navidad"
                                    value={nombre}
                                    onChange={(evento) =>
                                        setNombre(evento.target.value)
                                    }
                                    autoFocus
                                    required
                                />
                                <InputError
                                    message={errors.nombre ?? errors.tipo}
                                />
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
                                    data-test="recordatorio-plantilla-crear"
                                >
                                    Crear plantilla
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** Confirma la eliminación de una plantilla; si estaba en uso, se vuelve a la predeterminada. */
export function EliminarPlantillaDialog({
    plantilla,
    enUso,
    open,
    onOpenChange,
    onEliminada,
}: {
    plantilla: PlantillaPersonalizada;
    enUso: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onEliminada: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...RecordatorioPlantillaController.destroy.form(
                        plantilla.id,
                    )}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        onOpenChange(false);
                        onEliminada();
                    }}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar plantilla?</DialogTitle>
                                <DialogDescription>
                                    Se eliminará la plantilla{' '}
                                    <strong>"{plantilla.nombre}"</strong>.
                                    {enUso
                                        ? ' Está en uso: el correo volverá a enviarse con la predeterminada.'
                                        : ''}
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    type="submit"
                                    loading={processing}
                                    data-test="recordatorio-plantilla-eliminar-confirmar"
                                >
                                    Eliminar plantilla
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
