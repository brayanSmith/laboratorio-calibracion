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
import { colorEstadoIngreso, estadosIngreso } from '@/lib/estados-ingreso';
import type {
    EquipoProgramacionBusquedaItem,
    Ingreso,
    IngresoOption,
} from '@/types';

type Props = {
    ingreso: Ingreso | null;
    novedadesIngreso: IngresoOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function Dato({
    etiqueta,
    children,
}: {
    etiqueta: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1">
            <Label className="text-xs text-muted-foreground">{etiqueta}</Label>
            <div className="text-sm">{children}</div>
        </div>
    );
}

/**
 * Detalle de solo lectura de un ingreso ya recibido: quién lo recibió y entregó, la firma,
 * la novedad general y, por cada equipo, si llegó o no con sus novedades.
 */
export default function VerIngresoModal({
    ingreso,
    novedadesIngreso,
    open,
    onOpenChange,
}: Props) {
    if (!ingreso) {
        return null;
    }

    const novedadNombre = (id: number | null) =>
        id
            ? (novedadesIngreso.find((novedad) => novedad.id === id)?.nombre ??
              null)
            : null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[85vh] overflow-y-auto sm:max-w-lg"
                data-test="ver-ingreso-modal"
            >
                <DialogHeader>
                    <DialogTitle>Detalle del ingreso</DialogTitle>
                    <DialogDescription>
                        {ingreso.bahias_nombre} · {ingreso.desde} al{' '}
                        {ingreso.hasta}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Dato etiqueta="Estado">
                        <Badge
                            className={
                                colorEstadoIngreso[ingreso.estado_ingreso]
                            }
                        >
                            {estadosIngreso.find(
                                (option) =>
                                    option.value === ingreso.estado_ingreso,
                            )?.label ?? ingreso.estado_ingreso}
                        </Badge>
                    </Dato>
                    <Dato etiqueta="Fecha de recepción">
                        {ingreso.fecha_recepcion ?? 'Sin registrar'}
                    </Dato>
                    <Dato etiqueta="Técnico que recibe">
                        {ingreso.tecnico_recibe_nombre ?? 'Sin asignar'}
                    </Dato>
                    <Dato etiqueta="Cliente que entrega">
                        {ingreso.cliente_entrega_nombre ?? 'Sin asignar'}
                    </Dato>
                    <Dato etiqueta="Novedad general">
                        {ingreso.novedad || 'Sin novedad'}
                    </Dato>
                </div>

                {ingreso.firma_url ? (
                    <Dato etiqueta="Firma del cliente">
                        <img
                            src={ingreso.firma_url}
                            alt="Firma del cliente que entrega"
                            className="h-24 rounded-md border bg-white object-contain p-1"
                        />
                    </Dato>
                ) : null}

                <div className="space-y-2">
                    <Label className="text-xs text-muted-foreground">
                        Equipos ({ingreso.equipo_programaciones.length})
                    </Label>

                    {ingreso.equipo_programaciones.length > 0 ? (
                        <ul className="divide-y rounded-lg border">
                            {ingreso.equipo_programaciones.map((item) => (
                                <EquipoDetalle
                                    key={item.id}
                                    item={item}
                                    novedad={novedadNombre(
                                        item.novedad_ingreso_id,
                                    )}
                                />
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Este ingreso no tiene equipos.
                        </p>
                    )}
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

function EquipoDetalle({
    item,
    novedad,
}: {
    item: EquipoProgramacionBusquedaItem;
    novedad: string | null;
}) {
    return (
        <li
            className="space-y-1 px-3 py-2 text-sm"
            data-test="ver-ingreso-equipo"
        >
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="font-medium">
                        {item.equipo.codigo} · {item.equipo.modelo}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {item.equipo.tipo_equipo.nombre}
                        {item.tipo_mantenimiento === 'CORRECTIVO'
                            ? ' · Correctivo'
                            : ''}
                    </p>
                </div>
                <Badge
                    className={
                        item.ingresado
                            ? '!border-transparent !bg-emerald-500 !text-white'
                            : '!border-transparent !bg-red-500 !text-white'
                    }
                >
                    {item.ingresado ? 'Ingresó' : 'No ingresó'}
                </Badge>
            </div>

            {item.falla_detectada ? (
                <p className="text-xs">
                    <span className="text-muted-foreground">Falla:</span>{' '}
                    {item.falla_detectada}
                </p>
            ) : null}
            {novedad ? (
                <p className="text-xs">
                    <span className="text-muted-foreground">Novedad:</span>{' '}
                    {novedad}
                </p>
            ) : null}
            {item.observacion_no_ingreso ? (
                <p className="text-xs">
                    <span className="text-muted-foreground">Observación:</span>{' '}
                    {item.observacion_no_ingreso}
                </p>
            ) : null}
            {item.re_agendar && item.datos_re_agendamiento ? (
                <p className="text-xs">
                    <span className="text-muted-foreground">
                        Re-agendado para:
                    </span>{' '}
                    {item.datos_re_agendamiento.fecha_proximo_agendamiento}
                </p>
            ) : null}
        </li>
    );
}
