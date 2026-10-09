import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ServicioTerceroController from '@/actions/App/Http/Controllers/ServicioTerceroController';
import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import EquipoServicioCard from '@/components/servicio-terceros/equipo-servicio-card';
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
import type { MantenimientoOption, ServicioTerceroListado } from '@/types';

type Props = {
    servicio: ServicioTerceroListado | null;
    empresasTerceras: MantenimientoOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** Modal para editar un servicio de tercero: la empresa que lo realiza y el
 * documento (PDF) con la documentación del servicio. También se abre al
 * confirmar "Iniciar", para que se suba la documentación. */
export default function ServicioTerceroModal({
    servicio,
    empresasTerceras,
    open,
    onOpenChange,
}: Props) {
    const [empresaTerceroId, setEmpresaTerceroId] = useState(
        servicio?.empresa_tercero_id.toString() ?? '',
    );

    if (!servicio) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <Form
                    key={`${servicio.id}-${String(open)}`}
                    {...ServicioTerceroController.update.form(servicio.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Servicio de tercero</DialogTitle>
                                <DialogDescription>
                                    {servicio.orden_trabajo_codigo} ·{' '}
                                    {servicio.equipo_codigo} ·{' '}
                                    {servicio.equipo_modelo}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-6">
                                <EquipoServicioCard servicio={servicio} />

                                <div className="grid gap-2">
                                    <Label htmlFor="empresa_tercero_id">
                                        Empresa tercera
                                    </Label>
                                    <Combobox
                                        id="empresa_tercero_id"
                                        value={empresaTerceroId}
                                        onValueChange={setEmpresaTerceroId}
                                        options={empresasTerceras.map(
                                            (empresa) => ({
                                                id: empresa.id,
                                                label: empresa.nombre,
                                            }),
                                        )}
                                        placeholder="Selecciona una empresa"
                                        searchPlaceholder="Buscar empresa..."
                                    />
                                    <input
                                        type="hidden"
                                        name="empresa_tercero_id"
                                        value={empresaTerceroId}
                                    />
                                    <InputError
                                        message={errors.empresa_tercero_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="pdf_servicio">
                                        Documentación del servicio (PDF)
                                    </Label>
                                    <Input
                                        id="pdf_servicio"
                                        name="pdf_servicio"
                                        type="file"
                                        accept="application/pdf"
                                        data-test="servicio-tercero-pdf-input"
                                    />
                                    {servicio.pdf_url ? (
                                        <a
                                            href={servicio.pdf_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-sm text-primary underline"
                                        >
                                            Ver documento actual
                                        </a>
                                    ) : null}
                                    <InputError message={errors.pdf_servicio} />
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="servicio-tercero-update-submit"
                                >
                                    Guardar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
