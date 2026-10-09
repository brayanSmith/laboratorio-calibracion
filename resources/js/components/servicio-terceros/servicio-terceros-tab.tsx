import { CheckCircle2, Pencil, Play, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import DeleteServicioTerceroModal from '@/components/servicio-terceros/delete-servicio-tercero-modal';
import FinalizarServicioTerceroModal from '@/components/servicio-terceros/finalizar-servicio-tercero-modal';
import IniciarServicioTerceroModal from '@/components/servicio-terceros/iniciar-servicio-tercero-modal';
import ServicioTerceroModal from '@/components/servicio-terceros/servicio-tercero-modal';
import TiempoTranscurrido from '@/components/servicio-terceros/tiempo-transcurrido';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { MantenimientoOption, ServicioTerceroListado } from '@/types';

type Props = {
    servicios: ServicioTerceroListado[];
    empresasTerceras: MantenimientoOption[];
    canEdit: boolean;
    canDelete: boolean;
    searchPlaceholder: string;
    emptyMessage: string;
};

const columnHelper = createDataTableColumnHelper<ServicioTerceroListado>();

/** Tabla de servicios de tercero (mantenimiento o calibración) con sus acciones:
 * iniciar, finalizar, editar y eliminar, y las modales de cada una. */
export default function ServicioTercerosTab({
    servicios,
    empresasTerceras,
    canEdit,
    canDelete,
    searchPlaceholder,
    emptyMessage,
}: Props) {
    const [editId, setEditId] = useState<number | null>(null);
    const edit = servicios.find((servicio) => servicio.id === editId) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<ServicioTerceroListado | null>(
        null,
    );
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [iniciandoId, setIniciandoId] = useState<number | null>(null);
    const iniciando =
        servicios.find((servicio) => servicio.id === iniciandoId) ?? null;
    const [iniciarOpen, setIniciarOpen] = useState(false);
    const [finalizandoId, setFinalizandoId] = useState<number | null>(null);
    const finalizando =
        servicios.find((servicio) => servicio.id === finalizandoId) ?? null;
    const [finalizarOpen, setFinalizarOpen] = useState(false);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('fecha', { header: 'Fecha' }),
                columnHelper.accessor('orden_trabajo_codigo', {
                    header: 'Orden de trabajo',
                }),
                columnHelper.display({
                    id: 'equipo',
                    header: 'Equipo',
                    cell: ({ row }) => (
                        <span className="font-medium">
                            {row.original.equipo_codigo} ·{' '}
                            {row.original.equipo_modelo}
                        </span>
                    ),
                }),
                columnHelper.display({
                    id: 'cliente',
                    header: 'Cliente',
                    cell: ({ row }) => row.original.cliente_nombre ?? '—',
                }),
                columnHelper.accessor('empresa_tercero_nombre', {
                    header: 'Empresa tercera',
                }),
                columnHelper.accessor('estado_final_equipo', {
                    header: 'Estado',
                    cell: ({ row }) => {
                        const { estado_final_equipo, tiempo_servicio_inicio } =
                            row.original;

                        if (estado_final_equipo) {
                            return (
                                <Badge
                                    className={
                                        estado_final_equipo === 'APROBADO'
                                            ? 'bg-emerald-500 text-white'
                                            : 'bg-red-500 text-white'
                                    }
                                >
                                    {estado_final_equipo === 'APROBADO'
                                        ? 'Aprobado'
                                        : 'Rechazado'}
                                </Badge>
                            );
                        }

                        return tiempo_servicio_inicio ? (
                            <TiempoTranscurrido
                                desde={tiempo_servicio_inicio}
                            />
                        ) : (
                            '—'
                        );
                    },
                }),
                columnHelper.accessor('re_agendar', {
                    header: 'Re-agendar',
                    cell: (info) => (info.getValue() ? 'Sí' : 'No'),
                }),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    cell: ({ row }) => {
                        const servicio = row.original;
                        const finalizado =
                            servicio.estado_final_equipo !== null;
                        const iniciado =
                            servicio.tiempo_servicio_inicio !== null;

                        return (
                            <div className="flex items-center justify-end gap-2">
                                {canEdit && !finalizado && !iniciado ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        data-test="servicio-tercero-iniciar-button"
                                        onClick={() => {
                                            setIniciandoId(servicio.id);
                                            setIniciarOpen(true);
                                        }}
                                    >
                                        <Play className="h-4 w-4" /> Iniciar
                                    </Button>
                                ) : null}
                                {canEdit && !finalizado && iniciado ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        data-test="servicio-tercero-finalizar-button"
                                        onClick={() => {
                                            setFinalizandoId(servicio.id);
                                            setFinalizarOpen(true);
                                        }}
                                    >
                                        <CheckCircle2 className="h-4 w-4" />{' '}
                                        Finalizar
                                    </Button>
                                ) : null}
                                {canEdit ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        data-test="servicio-tercero-edit-button"
                                        onClick={() => {
                                            setEditId(servicio.id);
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
                                        data-test="servicio-tercero-delete-button"
                                        onClick={() => {
                                            setDeleting(servicio);
                                            setDeleteOpen(true);
                                        }}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                ) : null}
                            </div>
                        );
                    },
                }),
            ]),
        [canEdit, canDelete],
    );

    return (
        <>
            <DataTable
                data={servicios}
                columns={columns}
                searchPlaceholder={searchPlaceholder}
                emptyMessage={emptyMessage}
                rowTestId="servicio-tercero-row"
            />

            <ServicioTerceroModal
                key={editId ?? 'none'}
                servicio={edit}
                empresasTerceras={empresasTerceras}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteServicioTerceroModal
                servicio={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
            <IniciarServicioTerceroModal
                servicio={iniciando}
                open={iniciarOpen}
                onOpenChange={setIniciarOpen}
            />
            <FinalizarServicioTerceroModal
                servicio={finalizando}
                open={finalizarOpen}
                onOpenChange={setFinalizarOpen}
            />
        </>
    );
}
