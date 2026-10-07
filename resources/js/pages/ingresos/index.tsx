import { Head } from '@inertiajs/react';
import {
    ClipboardCheck,
    Gauge,
    Pencil,
    Plus,
    Wrench,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import CreateIngresoModal from '@/components/ingresos/create-ingreso-modal';
import DeleteIngresoModal from '@/components/ingresos/delete-ingreso-modal';
import EditIngresoModal from '@/components/ingresos/edit-ingreso-modal';
import RecibirIngresoModal from '@/components/ingresos/recibir-ingreso-modal';
import AgendarCalibracionesModal from '@/components/ordenes-trabajo/agendar-calibraciones-modal';
import AgendarMantenimientoModal from '@/components/ordenes-trabajo/agendar-mantenimiento-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { colorEstadoIngreso, estadosIngreso } from '@/lib/estados-ingreso';
import { index } from '@/routes/ingresos';
import type { Ingreso, IngresoOption, IngresoOptions } from '@/types';

type Props = IngresoOptions & {
    ingresos: Ingreso[];
    /** Empresas terceras a las que se les puede asignar un mantenimiento. */
    empresasTerceras: IngresoOption[];
};

const columnHelper = createDataTableColumnHelper<Ingreso>();

export default function IngresosIndex({
    ingresos,
    bahias,
    tecnicos,
    clientes,
    novedadesIngreso,
    empresasTerceras,
}: Props) {
    const { can } = usePermissions();
    const canEdit = can('ingresos.editar');
    const canDelete = can('ingresos.eliminar');
    const options: IngresoOptions = {
        bahias,
        tecnicos,
        clientes,
        novedadesIngreso,
    };
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing =
        ingresos.find((ingreso) => ingreso.id === editingId) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<Ingreso | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [recibiendoId, setRecibiendoId] = useState<number | null>(null);
    const recibiendo =
        ingresos.find((ingreso) => ingreso.id === recibiendoId) ?? null;
    const [recibirOpen, setRecibirOpen] = useState(false);
    const [agendarMantenimientoOpen, setAgendarMantenimientoOpen] =
        useState(false);
    const [agendarCalibracionesOpen, setAgendarCalibracionesOpen] =
        useState(false);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('desde', { header: 'Desde' }),
                columnHelper.accessor('hasta', { header: 'Hasta' }),
                columnHelper.accessor('bahia_nombre', { header: 'Bahía' }),
                columnHelper.accessor('cliente_entrega_nombre', {
                    header: 'Cliente',
                    cell: (info) => (
                        <span className="font-medium">
                            {info.getValue() ?? (
                                <span className="text-muted-foreground">
                                    Sin asignar
                                </span>
                            )}
                        </span>
                    ),
                }),
                columnHelper.accessor('tecnico_recibe_nombre', {
                    header: 'Técnico',
                    cell: (info) =>
                        info.getValue() ?? (
                            <span className="text-muted-foreground">
                                Sin asignar
                            </span>
                        ),
                }),
                columnHelper.accessor('estado_ingreso', {
                    header: 'Estado',
                    cell: (info) => (
                        <Badge className={colorEstadoIngreso[info.getValue()]}>
                            {estadosIngreso.find(
                                (option) => option.value === info.getValue(),
                            )?.label ?? info.getValue()}
                        </Badge>
                    ),
                }),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    cell: ({ row }) => (
                        <div className="flex items-center justify-end gap-2">
                            {canEdit ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    data-test="ingreso-recibir-button"
                                    onClick={() => {
                                        setRecibiendoId(row.original.id);
                                        setRecibirOpen(true);
                                    }}
                                >
                                    <ClipboardCheck className="h-4 w-4" />{' '}
                                    Recibir
                                </Button>
                            ) : null}
                            {canEdit ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    data-test="ingreso-edit-button"
                                    onClick={() => {
                                        setEditingId(row.original.id);
                                        setEditOpen(true);
                                    }}
                                >
                                    <Pencil className="h-4 w-4" />
                                </Button>
                            ) : null}
                            {canDelete ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    data-test="ingreso-delete-button"
                                    onClick={() => {
                                        setDeleting(row.original);
                                        setDeleteOpen(true);
                                    }}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            ) : null}
                        </div>
                    ),
                }),
            ]),
        [canEdit, canDelete],
    );

    return (
        <>
            <Head title="Ingresos" />

            <h1 className="sr-only">Ingresos</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Ingresos"
                        description="Registra la recepción de equipos de tus clientes en el laboratorio"
                    />

                    <div className="flex items-center gap-2">
                        {can('equipos.editar') ? (
                            <Button
                                variant="outline"
                                data-test="agendar-mantenimiento-button"
                                onClick={() =>
                                    setAgendarMantenimientoOpen(true)
                                }
                            >
                                <Wrench /> Agendar Mantenimiento
                            </Button>
                        ) : null}

                        {can('equipos.editar') ? (
                            <Button
                                variant="outline"
                                data-test="agendar-calibraciones-button"
                                onClick={() =>
                                    setAgendarCalibracionesOpen(true)
                                }
                            >
                                <Gauge /> Agendar Calibraciones
                            </Button>
                        ) : null}

                        {can('ingresos.crear') ? (
                            <CreateIngresoModal options={options}>
                                <Button data-test="ingresos-new-button">
                                    <Plus /> Nuevo ingreso
                                </Button>
                            </CreateIngresoModal>
                        ) : null}
                    </div>
                </div>

                <DataTable
                    data={ingresos}
                    columns={columns}
                    searchPlaceholder="Buscar ingreso..."
                    emptyMessage="Aún no has registrado ingresos."
                    rowTestId="ingreso-row"
                />
            </div>

            <EditIngresoModal
                ingreso={editing}
                options={options}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <RecibirIngresoModal
                ingreso={recibiendo}
                tecnicos={tecnicos}
                clientes={clientes}
                novedadesIngreso={novedadesIngreso}
                open={recibirOpen}
                onOpenChange={setRecibirOpen}
            />
            <DeleteIngresoModal
                ingreso={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
            <AgendarMantenimientoModal
                tecnicos={tecnicos}
                empresasTerceras={empresasTerceras}
                open={agendarMantenimientoOpen}
                onOpenChange={setAgendarMantenimientoOpen}
            />
            <AgendarCalibracionesModal
                tecnicos={tecnicos}
                empresasTerceras={empresasTerceras}
                open={agendarCalibracionesOpen}
                onOpenChange={setAgendarCalibracionesOpen}
            />
        </>
    );
}

IngresosIndex.layout = {
    breadcrumbs: [
        {
            title: 'Ingresos',
            href: index(),
        },
    ],
};
