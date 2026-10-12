import { FileText } from 'lucide-react';
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
import type { ServicioTerceroListado } from '@/types';

type Props = {
    servicio: ServicioTerceroListado | null;
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

/** Resumen de solo lectura de un servicio de tercero ya finalizado. */
export default function VerServicioTerceroModal({
    servicio,
    open,
    onOpenChange,
}: Props) {
    if (!servicio) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="sm:max-w-lg"
                data-test="ver-servicio-tercero-modal"
            >
                <DialogHeader>
                    <DialogTitle>Detalle del servicio de tercero</DialogTitle>
                    <DialogDescription>
                        {servicio.equipo_codigo} · {servicio.equipo_modelo}
                        {servicio.cliente_nombre
                            ? ` · ${servicio.cliente_nombre}`
                            : ''}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Dato etiqueta="Fecha">{servicio.fecha}</Dato>
                    <Dato etiqueta="Orden de trabajo">
                        {servicio.orden_trabajo_codigo}
                    </Dato>
                    <Dato etiqueta="Empresa tercera">
                        {servicio.empresa_tercero_nombre}
                    </Dato>
                    <Dato etiqueta="Estado final del equipo">
                        {servicio.estado_final_equipo ? (
                            <Badge
                                className={
                                    servicio.estado_final_equipo === 'APROBADO'
                                        ? '!border-transparent !bg-emerald-500 !text-white'
                                        : '!border-transparent !bg-red-500 !text-white'
                                }
                            >
                                {servicio.estado_final_equipo === 'APROBADO'
                                    ? 'Aprobado'
                                    : 'Rechazado'}
                            </Badge>
                        ) : (
                            <span className="text-muted-foreground">
                                Sin registrar
                            </span>
                        )}
                    </Dato>
                    <Dato etiqueta="Re-agendar">
                        {servicio.re_agendar ? 'Sí' : 'No'}
                    </Dato>
                    <Dato etiqueta="Documento del servicio">
                        {servicio.pdf_url ? (
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={servicio.pdf_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <FileText className="h-4 w-4" /> Ver PDF
                                </a>
                            </Button>
                        ) : (
                            <span className="text-muted-foreground">
                                Sin documento
                            </span>
                        )}
                    </Dato>
                </div>

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
