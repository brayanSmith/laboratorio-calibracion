import type { ReactNode } from 'react';
import { CheckCircle2, XCircle } from 'lucide-react';
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
import {
    colorEstadoEquipoMantenimiento,
    colorEstadoMantenimiento,
    estadosEquipoMantenimiento,
    estadosMantenimiento,
} from '@/lib/estados-mantenimiento';
import type { EstadoEquipoMantenimiento, Mantenimiento } from '@/types';

type Props = {
    mantenimiento: Mantenimiento | null;
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

function Seccion({
    titulo,
    children,
}: {
    titulo: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-2">
            <Label>{titulo}</Label>
            {children}
        </div>
    );
}

function EstadoEquipo({
    estado,
}: {
    estado: EstadoEquipoMantenimiento | null;
}) {
    if (!estado) {
        return <span className="text-muted-foreground">Sin registrar</span>;
    }

    return (
        <Badge className={colorEstadoEquipoMantenimiento[estado]}>
            {estadosEquipoMantenimiento.find(
                (option) => option.value === estado,
            )?.label ?? estado}
        </Badge>
    );
}

/**
 * Resumen de solo lectura de un mantenimiento ya realizado: estado del equipo antes y
 * después, checklist, defectos, ítems usados, comentarios y fotos.
 */
export default function VerMantenimientoModal({
    mantenimiento,
    open,
    onOpenChange,
}: Props) {
    if (!mantenimiento) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[90vh] overflow-y-auto sm:max-w-3xl"
                data-test="ver-mantenimiento-modal"
            >
                <DialogHeader>
                    <DialogTitle>Detalle del mantenimiento</DialogTitle>
                    <DialogDescription>
                        {mantenimiento.equipo.codigo} ·{' '}
                        {mantenimiento.equipo.tipo_equipo.nombre} ·{' '}
                        {mantenimiento.equipo.modelo}
                        {mantenimiento.equipo.cliente
                            ? ` · ${mantenimiento.equipo.cliente.nombre}`
                            : ''}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Dato etiqueta="Estado">
                        <Badge
                            className={
                                colorEstadoMantenimiento[
                                    mantenimiento.estado_mantenimiento
                                ]
                            }
                        >
                            {estadosMantenimiento.find(
                                (option) =>
                                    option.value ===
                                    mantenimiento.estado_mantenimiento,
                            )?.label ?? mantenimiento.estado_mantenimiento}
                        </Badge>
                    </Dato>
                    <Dato etiqueta="Fecha">
                        {mantenimiento.fecha_mantenimiento}
                    </Dato>
                    <Dato etiqueta="Técnico">
                        {mantenimiento.tecnico_nombre}
                    </Dato>
                    <Dato etiqueta="Orden de trabajo">
                        {mantenimiento.orden_trabajo_codigo}
                    </Dato>
                    <Dato etiqueta="Tipo de mantenimiento">
                        {mantenimiento.tipo_mantenimiento === 'CORRECTIVO'
                            ? 'Correctivo'
                            : 'Preventivo'}
                    </Dato>
                    <Dato etiqueta="Firmado">
                        {mantenimiento.firmado ? 'Sí' : 'No'}
                    </Dato>
                    <Dato etiqueta="Estado inicial del equipo">
                        <EstadoEquipo
                            estado={mantenimiento.estado_inicial_equipo}
                        />
                    </Dato>
                    <Dato etiqueta="Estado final del equipo">
                        <EstadoEquipo
                            estado={mantenimiento.estado_final_equipo}
                        />
                    </Dato>
                    <Dato etiqueta="Novedad">
                        {mantenimiento.novedad_nombre ?? 'Sin novedad'}
                    </Dato>
                    <div className="sm:col-span-3">
                        <Dato etiqueta="Descripción">
                            {mantenimiento.descripcion || 'Sin descripción'}
                        </Dato>
                    </div>
                </div>

                {mantenimiento.checklist.length > 0 ? (
                    <Seccion titulo="Checklist">
                        <ul className="divide-y rounded-lg border">
                            {mantenimiento.checklist.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex items-start gap-2 px-3 py-2 text-sm"
                                >
                                    {item.cumple ? (
                                        <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                                    ) : (
                                        <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
                                    )}
                                    <div>
                                        <p>{item.nombre}</p>
                                        {item.observacion ? (
                                            <p className="text-xs text-muted-foreground">
                                                {item.observacion}
                                            </p>
                                        ) : null}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Seccion>
                ) : null}

                {mantenimiento.defectos.length > 0 ? (
                    <Seccion titulo="Defectos identificados">
                        <ul className="divide-y rounded-lg border">
                            {mantenimiento.defectos.map((defecto) => (
                                <li
                                    key={defecto.id}
                                    className="space-y-1 px-3 py-2 text-sm"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <p>{defecto.defecto_identificado}</p>
                                        <Badge variant="secondary">
                                            Riesgo{' '}
                                            {defecto.nivel_riesgo.toLowerCase()}
                                        </Badge>
                                    </div>
                                    {defecto.accion_correctiva ? (
                                        <p className="text-xs text-muted-foreground">
                                            Acción correctiva:{' '}
                                            {defecto.accion_correctiva}
                                        </p>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    </Seccion>
                ) : null}

                {mantenimiento.items_usados.length > 0 ? (
                    <Seccion titulo="Ítems usados">
                        <ul className="divide-y rounded-lg border">
                            {mantenimiento.items_usados.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex items-center justify-between gap-2 px-3 py-2 text-sm"
                                >
                                    <span>
                                        {item.item_codigo} · {item.item_nombre}
                                    </span>
                                    <span className="font-mono">
                                        x{Number(item.cantidad)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Seccion>
                ) : null}

                {mantenimiento.comentarios.length > 0 ? (
                    <Seccion titulo="Comentarios">
                        <ul className="divide-y rounded-lg border">
                            {mantenimiento.comentarios.map((comentario) => (
                                <li
                                    key={comentario.id}
                                    className="space-y-1 px-3 py-2 text-sm"
                                >
                                    <p>{comentario.comentario}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {comentario.autor} ·{' '}
                                        {new Date(
                                            comentario.fecha,
                                        ).toLocaleString('es-PE', {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        })}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    </Seccion>
                ) : null}

                {mantenimiento.galeria.length > 0 ? (
                    <Seccion titulo="Fotos">
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {mantenimiento.galeria.map((foto) => (
                                <figure key={foto.id} className="space-y-1">
                                    <img
                                        src={foto.imagen_url}
                                        alt={
                                            foto.descripcion ??
                                            'Foto del mantenimiento'
                                        }
                                        className="aspect-square w-full rounded-md border object-cover"
                                    />
                                    {foto.descripcion ? (
                                        <figcaption className="text-xs text-muted-foreground">
                                            {foto.descripcion}
                                        </figcaption>
                                    ) : null}
                                </figure>
                            ))}
                        </div>
                    </Seccion>
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
