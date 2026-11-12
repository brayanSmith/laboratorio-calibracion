import { Head } from '@inertiajs/react';
import {
    Boxes,
    CalendarClock,
    CheckCircle2,
    Clock,
    Eye,
    PackageCheck,
    Pencil,
    Trash2,
    Truck,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import BotonAccion from '@/components/boton-accion';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import DeleteDespachoModal from '@/components/despachos/delete-despacho-modal';
import DespacharDespachoModal from '@/components/despachos/despachar-despacho-modal';
import EditDespachoModal from '@/components/despachos/edit-despacho-modal';
import ReagendarDespachoModal from '@/components/despachos/reagendar-despacho-modal';
import VerDespachoModal from '@/components/despachos/ver-despacho-modal';
import Heading from '@/components/heading';
import PlazoServicioCelda from '@/components/plazo-servicio-celda';
import TarjetasEstado, {
    useFiltroPorTarjeta,
} from '@/components/tarjetas-estado';
import type { TarjetaEstado } from '@/components/tarjetas-estado';
import { Badge } from '@/components/ui/badge';
import { usePermissions } from '@/hooks/use-permissions';
import { OPCIONES_SI_NO } from '@/lib/data-table';
import { hoyLocal } from '@/lib/plazo-servicio';
import { index } from '@/routes/despachos';
import type { Despacho, DespachoOptions } from '@/types';
import {
    CeldaEquipo,
    textoEquipo,
} from '@/components/equipos/celdas-equipo';

type Props = DespachoOptions & {
    despachos: Despacho[];
};

const columnHelper = createDataTableColumnHelper<Despacho>();

/** Sin entregar y con la fecha programada ya pasada: se puede re-agendar. */
function despachoVencido(despacho: Despacho): boolean {
    return (
        !despacho.entrega_recibida &&
        despacho.fecha_programada !== null &&
        despacho.fecha_programada < hoyLocal()
    );
}

export default function DespachosIndex({
    despachos,
    tecnicos,
    clientes,
    novedadesDespacho,
}: Props) {
    const { can } = usePermissions();
    const canEdit = can('despachos.editar');
    const canDelete = can('despachos.eliminar');
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing =
        despachos.find((despacho) => despacho.id === editingId) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<Despacho | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [despachandoId, setDespachandoId] = useState<number | null>(null);
    const despachando =
        despachos.find((despacho) => despacho.id === despachandoId) ?? null;
    const [despacharOpen, setDespacharOpen] = useState(false);
    const [viendoId, setViendoId] = useState<number | null>(null);
    const viendo =
        despachos.find((despacho) => despacho.id === viendoId) ?? null;
    const [verOpen, setVerOpen] = useState(false);
    const [reagendandoId, setReagendandoId] = useState<number | null>(null);
    const reagendando =
        despachos.find((despacho) => despacho.id === reagendandoId) ?? null;
    const [reagendarOpen, setReagendarOpen] = useState(false);
    const { claveTabla, filtrosIniciales, tarjetaActiva, ver } =
        useFiltroPorTarjeta();

    const contar = (estado: Despacho['estado_despacho']) =>
        despachos.filter((despacho) => despacho.estado_despacho === estado)
            .length;
    const vencidos = despachos.filter(despachoVencido).length;
    const tarjetas: TarjetaEstado[] = [
        {
            id: 'despachos-todos',
            titulo: 'Todos los despachos',
            valor: despachos.length,
            icono: Boxes,
            tono: 'sky',
            filtros: [],
        },
        {
            id: 'despachos-pendientes',
            titulo: 'Pendientes',
            valor: contar('PENDIENTE'),
            icono: Clock,
            tono: 'amber',
            detalle: 'Sin autorizar la entrega',
            filtros: [{ id: 'entrega_autorizada', value: 'false' }],
        },
        {
            id: 'despachos-autorizados',
            titulo: 'Autorizados',
            valor: contar('AUTORIZADO'),
            icono: Truck,
            tono: 'blue',
            detalle:
                vencidos > 0
                    ? `Por entregar · ${vencidos} con el plazo vencido`
                    : 'Por entregar al cliente',
            filtros: [
                { id: 'entrega_autorizada', value: 'true' },
                { id: 'entrega_recibida', value: 'false' },
            ],
        },
        {
            id: 'despachos-entregados',
            titulo: 'Entregados',
            valor: contar('ENTREGADO'),
            icono: CheckCircle2,
            tono: 'emerald',
            filtros: [{ id: 'entrega_recibida', value: 'true' }],
        },
    ];

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('orden_trabajo_codigo', {
                    header: 'Orden de trabajo',
                }),
                // Como en la tabla de equipos: código y tipo; modelo y serie debajo.
                columnHelper.accessor(
                    (row) =>
                        textoEquipo(
                            row.equipo.codigo,
                            row.equipo.tipo_equipo.nombre,
                            row.equipo.modelo,
                            row.equipo.numero_serie,
                        ),
                    {
                        id: 'equipo',
                        header: 'Equipo',
                        cell: ({ row }) => (
                            <CeldaEquipo
                                codigo={row.original.equipo.codigo}
                                tipo={row.original.equipo.tipo_equipo.nombre}
                                modelo={row.original.equipo.modelo}
                                serie={row.original.equipo.numero_serie}
                            />
                        ),
                    },
                ),
                columnHelper.accessor((row) => row.equipo.cliente.nombre, {
                    id: 'cliente',
                    header: 'Cliente',
                    cell: ({ row }) => row.original.equipo.cliente.nombre,
                }),
                columnHelper.accessor('fecha_programada', {
                    header: 'Programado',
                    cell: ({ row }) => (
                        <div className="space-y-1">
                            <PlazoServicioCelda
                                fechaProgramada={row.original.fecha_programada}
                                fechaRealizacion={row.original.fecha_despacho}
                            />
                            {row.original.reagendamientos.length > 0 ? (
                                <p
                                    className="text-xs text-muted-foreground"
                                    data-test="despacho-reagendado"
                                >
                                    Re-agendado{' '}
                                    {row.original.reagendamientos.length === 1
                                        ? '1 vez'
                                        : `${row.original.reagendamientos.length} veces`}
                                </p>
                            ) : null}
                        </div>
                    ),
                }),
                columnHelper.accessor('tecnico_entrega_nombre', {
                    header: 'Técnico que entrega',
                    cell: (info) => info.getValue() ?? '—',
                }),
                columnHelper.accessor('cliente_recibe_nombre', {
                    header: 'Cliente que recibe',
                    cell: (info) => info.getValue() ?? '—',
                }),
                columnHelper.accessor('entrega_autorizada', {
                    meta: { filterOptions: OPCIONES_SI_NO },
                    header: 'Autorizada',
                    cell: (info) => (
                        <Badge
                            className={
                                info.getValue()
                                    ? '!border-transparent !bg-emerald-500 !text-white'
                                    : '!border-transparent !bg-red-500 !text-white'
                            }
                        >
                            {info.getValue() ? 'Sí' : 'No'}
                        </Badge>
                    ),
                }),
                columnHelper.accessor('entrega_recibida', {
                    meta: { filterOptions: OPCIONES_SI_NO },
                    header: 'Recibida',
                    cell: (info) => (
                        <Badge
                            className={
                                info.getValue()
                                    ? '!border-transparent !bg-emerald-500 !text-white'
                                    : '!border-transparent !bg-red-500 !text-white'
                            }
                        >
                            {info.getValue() ? 'Sí' : 'No'}
                        </Badge>
                    ),
                }),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    cell: ({ row }) => (
                        <div className="flex items-center justify-end gap-2">
                            {row.original.entrega_recibida ||
                            row.original.reagendamientos.length > 0 ? (
                                <BotonAccion
                                    icono={Eye}
                                    etiqueta="Ver"
                                    variant="outline"
                                    data-test="despacho-ver-button"
                                    onClick={() => {
                                        setViendoId(row.original.id);
                                        setVerOpen(true);
                                    }}
                                />
                            ) : null}
                            {canEdit &&
                            row.original.entrega_autorizada &&
                            !row.original.entrega_recibida ? (
                                <BotonAccion
                                    icono={PackageCheck}
                                    etiqueta="Despachar"
                                    className="!border-transparent !bg-emerald-600 !text-white hover:!bg-emerald-700"
                                    data-test="despacho-despachar-button"
                                    onClick={() => {
                                        setDespachandoId(row.original.id);
                                        setDespacharOpen(true);
                                    }}
                                />
                            ) : null}
                            {canEdit && despachoVencido(row.original) ? (
                                <BotonAccion
                                    icono={CalendarClock}
                                    etiqueta="Re-agendar"
                                    variant="outline"
                                    className="!border-red-300 !text-red-700 hover:!bg-red-50 dark:!border-red-900 dark:!text-red-300 dark:hover:!bg-red-950"
                                    data-test="despacho-reagendar-button"
                                    onClick={() => {
                                        setReagendandoId(row.original.id);
                                        setReagendarOpen(true);
                                    }}
                                />
                            ) : null}
                            {canEdit ? (
                                <BotonAccion
                                    icono={Pencil}
                                    etiqueta="Editar"
                                    data-test="despacho-edit-button"
                                    onClick={() => {
                                        setEditingId(row.original.id);
                                        setEditOpen(true);
                                    }}
                                />
                            ) : null}
                            {canDelete ? (
                                <BotonAccion
                                    icono={Trash2}
                                    etiqueta="Eliminar"
                                    data-test="despacho-delete-button"
                                    onClick={() => {
                                        setDeleting(row.original);
                                        setDeleteOpen(true);
                                    }}
                                />
                            ) : null}
                        </div>
                    ),
                }),
            ]),
        [canEdit, canDelete],
    );

    return (
        <>
            <Head title="Despachos" />

            <h1 className="sr-only">Despachos</h1>

            <div className="flex flex-col space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Despachos"
                    description="Despachos creados al finalizar una calibración, con quién entrega y recibe cada equipo"
                />

                <TarjetasEstado
                    tarjetas={tarjetas}
                    activa={tarjetaActiva}
                    onVer={ver}
                />

                <DataTable
                    key={claveTabla}
                    initialColumnFilters={filtrosIniciales}
                    data={despachos}
                    columns={columns}
                    searchPlaceholder="Buscar despacho..."
                    emptyMessage="Aún no hay despachos."
                    rowTestId="despacho-row"
                />
            </div>

            <EditDespachoModal
                despacho={editing}
                tecnicos={tecnicos}
                clientes={clientes}
                novedadesDespacho={novedadesDespacho}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <VerDespachoModal
                despacho={viendo}
                open={verOpen}
                onOpenChange={setVerOpen}
            />
            <ReagendarDespachoModal
                despacho={reagendando}
                open={reagendarOpen}
                onOpenChange={setReagendarOpen}
            />
            <DespacharDespachoModal
                despacho={despachando}
                clientes={clientes}
                novedadesDespacho={novedadesDespacho}
                open={despacharOpen}
                onOpenChange={setDespacharOpen}
            />
            <DeleteDespachoModal
                despacho={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

DespachosIndex.layout = {
    breadcrumbs: [
        {
            title: 'Despachos',
            href: index(),
        },
    ],
};
