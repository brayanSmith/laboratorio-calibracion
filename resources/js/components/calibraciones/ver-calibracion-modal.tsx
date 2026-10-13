import { Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import { certificado } from '@/actions/App/Http/Controllers/CalibracionController';
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
import { colorEstadoCalibracion } from '@/lib/estados-calibracion';
import type { Calibracion } from '@/types';

type Props = {
    calibracion: Calibracion | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const etiquetasEstado: Record<string, string> = {
    PENDIENTE: 'Pendiente',
    EN_PROCESO: 'En proceso',
    FINALIZADO: 'Finalizado',
    DEVOLVER_MANTENIMIENTO: 'Devolver a mantenimiento',
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

const vacio = <span className="text-muted-foreground">—</span>;

/**
 * Resumen de solo lectura de una calibración ya realizada: sus datos y la tabla de
 * resultados de la medición.
 */
export default function VerCalibracionModal({
    calibracion,
    open,
    onOpenChange,
}: Props) {
    if (!calibracion) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[90vh] overflow-y-auto sm:max-w-4xl"
                data-test="ver-calibracion-modal"
            >
                <DialogHeader>
                    <DialogTitle>Detalle de la calibración</DialogTitle>
                    <DialogDescription>
                        {calibracion.equipo.codigo} ·{' '}
                        {calibracion.equipo.tipo_equipo.nombre} ·{' '}
                        {calibracion.equipo.modelo}
                        {calibracion.equipo.cliente
                            ? ` · ${calibracion.equipo.cliente.nombre}`
                            : ''}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Dato etiqueta="Estado">
                        <Badge
                            className={
                                colorEstadoCalibracion[
                                    calibracion.estado_calibracion
                                ]
                            }
                        >
                            {etiquetasEstado[calibracion.estado_calibracion] ??
                                calibracion.estado_calibracion}
                        </Badge>
                    </Dato>
                    <Dato etiqueta="Fecha">
                        {calibracion.fecha_calibracion}
                    </Dato>
                    <Dato etiqueta="Técnico">{calibracion.tecnico_nombre}</Dato>
                    <Dato etiqueta="Orden de trabajo">
                        {calibracion.orden_trabajo_codigo}
                    </Dato>
                    <Dato etiqueta="Laboratorio">
                        {calibracion.laboratorio_nombre ?? vacio}
                    </Dato>
                    <Dato etiqueta="Solicitante">
                        {calibracion.solicitante_nombre ?? vacio}
                    </Dato>
                    <Dato etiqueta="Procedimiento">
                        {calibracion.procedimiento_nombre ?? vacio}
                    </Dato>
                    <Dato etiqueta="Temperatura">
                        {calibracion.temperatura ?? vacio}
                    </Dato>
                    <Dato etiqueta="Humedad">
                        {calibracion.humedad ?? vacio}
                    </Dato>
                    <Dato etiqueta="Ajustes requeridos">
                        {calibracion.ajustes_requeridos ? 'Sí' : 'No'}
                    </Dato>
                    <Dato etiqueta="Firmado">
                        {calibracion.firmado ? 'Sí' : 'No'}
                    </Dato>
                    <Dato etiqueta="Novedad">
                        {calibracion.novedad_nombre ?? 'Sin novedad'}
                    </Dato>
                </div>

                <div className="space-y-2">
                    <Label>Resultados</Label>

                    {calibracion.detalles_medicion.length > 0 ? (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-xs">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Valor de referencia
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Valor del instrumento
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Error encontrado
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            EMP
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Incertidumbre
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Resultado
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {calibracion.detalles_medicion.map(
                                        (detalle) => (
                                            <tr key={detalle.id}>
                                                <td className="px-3 py-2">
                                                    {detalle.valor_referencia}{' '}
                                                    {detalle.unidad_simbolo}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {detalle.valor_instrumento ??
                                                        '—'}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {detalle.error_encontrado ??
                                                        '—'}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {detalle.emp}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {detalle.incertidumbre}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {detalle.resultado_calibracion ? (
                                                        <Badge
                                                            className={
                                                                detalle.resultado_calibracion ===
                                                                'APROBADO'
                                                                    ? '!border-transparent !bg-emerald-500 !text-white'
                                                                    : '!border-transparent !bg-red-500 !text-white'
                                                            }
                                                        >
                                                            {detalle.resultado_calibracion ===
                                                            'APROBADO'
                                                                ? 'Aprobado'
                                                                : 'No aprobado'}
                                                        </Badge>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Esta calibración no tiene filas de resultados.
                        </p>
                    )}
                </div>

                <DialogFooter className="gap-2">
                    {calibracion.estado_calibracion === 'FINALIZADO' ? (
                        <Button variant="outline" asChild>
                            <a
                                href={certificado.url(calibracion.id)}
                                target="_blank"
                                rel="noreferrer"
                                data-test="imprimir-certificado-button"
                            >
                                <Printer className="h-4 w-4" /> Imprimir
                                certificado
                            </a>
                        </Button>
                    ) : null}
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
