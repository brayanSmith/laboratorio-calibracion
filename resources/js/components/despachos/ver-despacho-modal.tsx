import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
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
import { Label } from '@/components/ui/label';
import type { Despacho } from '@/types';

type Props = {
    despacho: Despacho | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function Dato({
    etiqueta,
    children,
}: {
    etiqueta: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1">
            <Label className="text-xs text-muted-foreground">{etiqueta}</Label>
            <div className="text-sm">{children}</div>
        </div>
    );
}

/** Detalle de solo lectura de un despacho cuya entrega ya fue recibida. */
export default function VerDespachoModal({
    despacho,
    open,
    onOpenChange,
}: Props) {
    if (!despacho) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[90vh] overflow-y-auto sm:max-w-xl"
                data-test="ver-despacho-modal"
            >
                <DialogHeader>
                    <DialogTitle>Detalle del despacho</DialogTitle>
                    <DialogDescription>
                        {despacho.equipo.codigo} ·{' '}
                        {despacho.equipo.tipo_equipo.nombre} ·{' '}
                        {despacho.equipo.modelo}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Dato etiqueta="Estado">
                        <Badge className="!border-transparent !bg-emerald-500 !text-white">
                            Entregado
                        </Badge>
                    </Dato>
                    <Dato etiqueta="Orden de trabajo">
                        {despacho.orden_trabajo_codigo}
                    </Dato>
                    <Dato etiqueta="Cliente del equipo">
                        {despacho.equipo.cliente.nombre}
                    </Dato>
                    <Dato etiqueta="Técnico que entrega">
                        {despacho.tecnico_entrega_nombre ?? 'Sin asignar'}
                    </Dato>
                    <Dato etiqueta="Cliente que recibe">
                        {despacho.cliente_recibe_nombre ?? 'Sin registrar'}
                    </Dato>
                    <Dato etiqueta="Novedad">
                        {despacho.novedad_nombre ?? 'Sin novedad'}
                    </Dato>
                </div>

                <Dato etiqueta="Firma del cliente">
                    {despacho.firma_url ? (
                        <img
                            src={despacho.firma_url}
                            alt="Firma del cliente que recibe"
                            className="h-24 rounded-md border bg-white object-contain p-1"
                        />
                    ) : (
                        <span className="text-muted-foreground">Sin firma</span>
                    )}
                </Dato>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Cerrar
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
