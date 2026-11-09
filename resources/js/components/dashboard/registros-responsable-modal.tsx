import { useMemo } from 'react';
import BarraCumplimiento from '@/components/dashboard/barra-cumplimiento';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cumplimientoDelRegistro } from '@/lib/cumplimiento-responsable';
import type { CumplimientoTiempo } from '@/lib/cumplimiento-responsable';
import { formatearHoras } from '@/lib/indicadores';
import type { DesempenoTecnicos } from '@/types';

type Registro = DesempenoTecnicos['servicios'][number] & {
    cumplimiento: CumplimientoTiempo | null;
    /** Para filtrar el cumplimiento por opción. */
    estado: 'CUMPLE' | 'NO_CUMPLE' | 'SIN_OBJETIVO';
};

type Props = {
    datos: DesempenoTecnicos;
    /** Responsable cuyos registros se ven; null con la ventana cerrada. */
    responsable: DesempenoTecnicos['responsables'][number] | null;
    /** Servicios que se están viendo en el indicador (mantenimiento, calibración). */
    servicios: string[];
    onOpenChange: (open: boolean) => void;
};

const columnHelper = createDataTableColumnHelper<Registro>();

const formatoFecha = new Intl.DateTimeFormat('es', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
});

/** Badge del servicio, con el color de su serie en los gráficos. */
export function BadgeServicio({
    servicio,
}: {
    servicio: 'MANTENIMIENTO' | 'CALIBRACION';
}) {
    return servicio === 'MANTENIMIENTO' ? (
        <Badge className="border-transparent bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300">
            Mantenimiento
        </Badge>
    ) : (
        <Badge className="border-transparent bg-violet-100 text-violet-800 dark:bg-violet-950/60 dark:text-violet-300">
            Calibración
        </Badge>
    );
}

const textoOGuion = (texto: string | null) => texto ?? '—';

const columns = columnHelper.columns([
    columnHelper.accessor('fecha', {
        header: 'Fecha',
        cell: (info) => (
            <span className="whitespace-nowrap tabular-nums">
                {formatoFecha.format(new Date(`${info.getValue()}T00:00:00Z`))}
            </span>
        ),
    }),
    columnHelper.accessor('tipo_servicio', {
        header: 'Servicio',
        meta: {
            filterOptions: [
                { value: 'MANTENIMIENTO', label: 'Mantenimiento' },
                { value: 'CALIBRACION', label: 'Calibración' },
            ],
        },
        cell: (info) => <BadgeServicio servicio={info.getValue()} />,
    }),
    columnHelper.accessor((fila) => textoOGuion(fila.orden_trabajo), {
        id: 'orden_trabajo',
        header: 'Orden de trabajo',
        cell: (info) => <span className="font-medium">{info.getValue()}</span>,
    }),
    columnHelper.accessor((fila) => textoOGuion(fila.equipo), {
        id: 'equipo',
        header: 'Equipo',
    }),
    columnHelper.accessor((fila) => textoOGuion(fila.tipo_equipo), {
        id: 'tipo_equipo',
        header: 'Tipo de equipo',
    }),
    columnHelper.accessor((fila) => textoOGuion(fila.magnitud), {
        id: 'magnitud',
        header: 'Magnitud',
    }),
    columnHelper.accessor((fila) => fila.horas ?? -1, {
        id: 'horas',
        header: 'Tiempo',
        cell: ({ row }) => (
            <span className="tabular-nums">
                {formatearHoras(row.original.horas)}
            </span>
        ),
    }),
    columnHelper.accessor('estado', {
        header: 'Cumplimiento',
        meta: {
            filterOptions: [
                { value: 'CUMPLE', label: 'Cumple' },
                { value: 'NO_CUMPLE', label: 'No cumple' },
                { value: 'SIN_OBJETIVO', label: 'Sin objetivo' },
            ],
        },
        // Se ordena por el porcentaje, no por el texto del estado.
        sortFn: (a, b) =>
            (a.original.cumplimiento?.porcentaje ?? -1) -
            (b.original.cumplimiento?.porcentaje ?? -1),
        cell: ({ row }) => {
            const cumplimiento = row.original.cumplimiento;

            return cumplimiento === null ? (
                <span
                    className="text-muted-foreground"
                    title="Sin objetivo o sin tiempo registrado"
                >
                    —
                </span>
            ) : (
                <BarraCumplimiento
                    porcentaje={cumplimiento.porcentaje}
                    cumple={cumplimiento.cumple}
                    detalle={`obj. ${formatearHoras(cumplimiento.objetivo)}`}
                />
            );
        },
    }),
]);

/**
 * Los servicios de un responsable en el periodo, en una tabla que se puede ordenar y
 * filtrar, cada uno con su cumplimiento de tiempo frente al objetivo de su tipo de equipo.
 */
export default function RegistrosResponsableModal({
    datos,
    responsable,
    servicios,
    onOpenChange,
}: Props) {
    const registros = useMemo<Registro[]>(() => {
        if (!responsable) {
            return [];
        }

        return datos.servicios
            .filter(
                (servicio) =>
                    servicio.responsable_tipo === responsable.tipo &&
                    servicio.responsable_id === responsable.responsable_id &&
                    servicios.includes(servicio.tipo_servicio),
            )
            .sort((a, b) => b.fecha.localeCompare(a.fecha))
            .map((servicio) => {
                const cumplimiento = cumplimientoDelRegistro(datos, servicio);

                return {
                    ...servicio,
                    cumplimiento,
                    estado:
                        cumplimiento === null
                            ? 'SIN_OBJETIVO'
                            : cumplimiento.cumple
                              ? 'CUMPLE'
                              : 'NO_CUMPLE',
                };
            });
    }, [datos, responsable, servicios]);

    return (
        <Dialog open={responsable !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle>
                        Registros de {responsable?.responsable}
                    </DialogTitle>
                    <DialogDescription>
                        Servicios finalizados en el periodo. El cumplimiento
                        compara el tiempo de cada uno con el objetivo de su tipo
                        de equipo en ese mes.
                    </DialogDescription>
                </DialogHeader>

                <DataTable
                    data={registros}
                    columns={columns}
                    searchPlaceholder="Buscar orden, equipo o tipo..."
                    emptyMessage="Sin registros de los servicios que se están viendo."
                    rowTestId="desempeno-registro-responsable"
                />
            </DialogContent>
        </Dialog>
    );
}
