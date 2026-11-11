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
import { formatearFecha, formatearFechaHora, mostrarFecha } from '@/lib/fechas';

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
            <div className="text-sm">{mostrarFecha(children)}</div>
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
                    <Dato etiqueta="Fecha programada">
                        {despacho.fecha_programada ?? 'Sin agendar'}
                    </Dato>
                    <Dato etiqueta="Fecha de despacho">
                        {despacho.fecha_despacho ?? 'Sin despachar'}
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

                {despacho.reagendamientos.length > 0 ? (
                    <Dato etiqueta="Re-agendamientos">
                        <ul
                            className="space-y-2"
                            data-test="despacho-historial-reagendamientos"
                        >
                            {despacho.reagendamientos.map((reagendamiento) => (
                                <li
                                    key={reagendamiento.id}
                                    className="rounded-md border px-3 py-2 text-sm"
                                >
                                    <p className="font-medium">
                                        {formatearFecha(reagendamiento.fecha_anterior)} →{' '}
                                        {formatearFecha(reagendamiento.fecha_nueva)}
                                    </p>
                                    <p>{reagendamiento.motivo}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatearFechaHora(reagendamiento.fecha)}{' '}
                                        · {reagendamiento.usuario ?? 'Sistema'}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    </Dato>
                ) : null}

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
