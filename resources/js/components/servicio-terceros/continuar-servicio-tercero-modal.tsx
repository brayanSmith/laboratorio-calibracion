import { Form } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useState } from 'react';
import ServicioTerceroController from '@/actions/App/Modules/ServiciosTerceros/Http/Controllers/ServicioTerceroController';
import CodigosServicioFields from '@/components/servicio-terceros/codigos-servicio-fields';
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
import type { ServicioTerceroListado } from '@/types';

export type CodigosServicio = { compra: string; despacho: string };

type Props = {
    servicio: ServicioTerceroListado | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Se llama con los códigos escritos al presionar "Finalizar", para abrir su modal. */
    onFinalizar: (codigos: CodigosServicio) => void;
};

type FormProps = Pick<Props, 'onOpenChange' | 'onFinalizar'> & {
    servicio: ServicioTerceroListado;
};

/** Se monta al abrir la modal, así los campos parten siempre de lo guardado. */
function ContinuarServicioTerceroForm({
    servicio,
    onOpenChange,
    onFinalizar,
}: FormProps) {
    const [codigoCompra, setCodigoCompra] = useState(
        servicio.codigo_compra ?? '',
    );
    const [codigoDespacho, setCodigoDespacho] = useState(
        servicio.codigo_despacho ?? '',
    );

    return (
        <Form
            {...ServicioTerceroController.guardarCodigos.form(servicio.id)}
            className="space-y-6"
            onSuccess={() => onOpenChange(false)}
        >
            {({ errors, processing }) => (
                <>
                    <DialogHeader>
                        <DialogTitle>Continuar servicio de tercero</DialogTitle>
                        <DialogDescription>
                            {servicio.orden_trabajo_codigo} ·{' '}
                            {servicio.equipo_codigo} ·{' '}
                            {servicio.empresa_tercero_nombre}
                        </DialogDescription>
                    </DialogHeader>

                    <EquipoServicioCard servicio={servicio} />

                    <CodigosServicioFields
                        codigoCompra={codigoCompra}
                        codigoDespacho={codigoDespacho}
                        onCodigoCompraChange={setCodigoCompra}
                        onCodigoDespachoChange={setCodigoDespacho}
                        errors={errors}
                    />

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            loading={processing}
                            data-test="servicio-tercero-guardar-codigos-button"
                        >
                            Guardar
                        </Button>
                        <Button
                            type="button"
                            className="!border-transparent !bg-blue-500 !text-white hover:!bg-blue-600"
                            data-test="servicio-tercero-continuar-finalizar-button"
                            onClick={() =>
                                onFinalizar({
                                    compra: codigoCompra,
                                    despacho: codigoDespacho,
                                })
                            }
                        >
                            <CheckCircle2 className="h-4 w-4" /> Finalizar
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

/** Modal del botón "Continuar" de un servicio de tercero de mantenimiento en curso:
 * pide el código de compra y el de despacho (opcionales) y permite guardarlos o pasar a
 * finalizar el servicio (ver ServicioTerceroController::guardarCodigos()). */
export default function ContinuarServicioTerceroModal({
    servicio,
    open,
    onOpenChange,
    onFinalizar,
}: Props) {
    if (!servicio) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <ContinuarServicioTerceroForm
                    key={`${servicio.id}-${String(open)}`}
                    servicio={servicio}
                    onOpenChange={onOpenChange}
                    onFinalizar={onFinalizar}
                />
            </DialogContent>
        </Dialog>
    );
}
