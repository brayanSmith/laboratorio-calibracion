import {
    CheckCircle2,
    Eye,
    FastForward,
    Pencil,
    Play,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import ContinuarServicioTerceroModal from '@/components/servicio-terceros/continuar-servicio-tercero-modal';
import type { CodigosServicio } from '@/components/servicio-terceros/continuar-servicio-tercero-modal';
import DeleteServicioTerceroModal from '@/components/servicio-terceros/delete-servicio-tercero-modal';
import FinalizarServicioTerceroModal from '@/components/servicio-terceros/finalizar-servicio-tercero-modal';
import IniciarServicioTerceroModal from '@/components/servicio-terceros/iniciar-servicio-tercero-modal';
import ServicioTerceroModal from '@/components/servicio-terceros/servicio-tercero-modal';
import TiempoTranscurrido from '@/components/servicio-terceros/tiempo-transcurrido';
import VerServicioTerceroModal from '@/components/servicio-terceros/ver-servicio-tercero-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { MantenimientoOption, ServicioTerceroListado } from '@/types';
import { OPCIONES_SI_NO } from '@/lib/data-table';
import { estadosFinalesEquipoTercero } from '@/lib/estado-final-equipo';

/** Pendiente en ámbar, en proceso en azul, finalizado en verde, como en mantenimiento y calibración. */
const estadosServicioTercero: Record<
    ServicioTerceroListado['estado_servicio'],
    { label: string; className: string }
> = {
    PENDIENTE: {
        label: 'Pendiente',
        className: '!border-transparent !bg-amber-500 !text-white',
    },
    EN_PROCESO: {
        label: 'En proceso',
        className: '!border-transparent !bg-blue-500 !text-white',
    },
    FINALIZADO: {
        label: 'Finalizado',
        className: '!border-transparent !bg-emerald-500 !text-white',
    },
};

type Props = {
    servicios: ServicioTerceroListado[];
    empresasTerceras: MantenimientoOption[];
    canEdit: boolean;
    canDelete: boolean;
    canIniciar: boolean;
    canFinalizar: boolean;
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
    canIniciar,
    canFinalizar,
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
    const [codigosBorrador, setCodigosBorrador] =
        useState<CodigosServicio | null>(null);
    const [continuandoId, setContinuandoId] = useState<number | null>(null);
    const continuando =
        servicios.find((servicio) => servicio.id === continuandoId) ?? null;
    const [continuarOpen, setContinuarOpen] = useState(false);
    const [viendoId, setViendoId] = useState<number | null>(null);
    const viendo =
        servicios.find((servicio) => servicio.id === viendoId) ?? null;
    const [verOpen, setVerOpen] = useState(false);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('fecha', { header: 'Fecha' }),
                columnHelper.accessor('orden_trabajo_codigo', {
                    header: 'Orden de trabajo',
                }),
                columnHelper.accessor(
                    (row) => `${row.equipo_codigo} ${row.equipo_modelo}`,
                    {
                        id: 'equipo',
                        header: 'Equipo',
                        cell: ({ row }) => (
                            <span className="font-medium">
                                {row.original.equipo_codigo} ·{' '}
                                {row.original.equipo_modelo}
                            </span>
                        ),
                    },
                ),
                columnHelper.accessor((row) => row.cliente_nombre ?? '', {
                    id: 'cliente',
                    header: 'Cliente',
                    cell: ({ row }) => row.original.cliente_nombre ?? '—',
                }),
                columnHelper.accessor('empresa_tercero_nombre', {
                    header: 'Laboratorio tercero',
                }),
                columnHelper.accessor('estado_servicio', {
                    meta: {
                        filterOptions: Object.entries(
                            estadosServicioTercero,
                        ).map(([value, { label }]) => ({ value, label })),
                    },
                    header: 'Estado',
                    cell: ({ row }) => {
                        const { estado_servicio, tiempo_servicio_inicio } =
                            row.original;
                        const estado = estadosServicioTercero[estado_servicio];

                        return (
                            <div className="space-y-1">
                                <Badge className={estado.className}>
                                    {estado.label}
                                </Badge>
                                {estado_servicio === 'EN_PROCESO' &&
                                tiempo_servicio_inicio ? (
                                    <TiempoTranscurrido
                                        desde={tiempo_servicio_inicio}
                                    />
                                ) : null}
                            </div>
                        );
                    },
                }),
                columnHelper.accessor('estado_final_equipo', {
                    meta: {
                        filterOptions: [
                            ...Object.entries(estadosFinalesEquipoTercero).map(
                                ([value, { label }]) => ({ value, label }),
                            ),
                            { value: 'null', label: 'Sin finalizar' },
                        ],
                    },
                    header: 'Estado final del equipo',
                    cell: (info) => {
                        const estadoFinal = info.getValue();

                        return estadoFinal ? (
                            <Badge
                                className={
                                    estadosFinalesEquipoTercero[estadoFinal]
                                        .className
                                }
                                data-test="servicio-tercero-estado-final-equipo"
                            >
                                {estadosFinalesEquipoTercero[estadoFinal].label}
                            </Badge>
                        ) : (
                            <span className="text-muted-foreground">—</span>
                        );
                    },
                }),
                columnHelper.accessor('re_agendar', {
                    meta: { filterOptions: OPCIONES_SI_NO },
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
                                {finalizado ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        data-test="servicio-tercero-ver-button"
                                        onClick={() => {
                                            setViendoId(servicio.id);
                                            setVerOpen(true);
                                        }}
                                    >
                                        <Eye className="h-4 w-4" /> Ver
                                    </Button>
                                ) : null}
                                {canIniciar && !finalizado && !iniciado ? (
                                    <Button
                                        size="sm"
                                        className="!border-transparent !bg-emerald-600 !text-white hover:!bg-emerald-700"
                                        data-test="servicio-tercero-iniciar-button"
                                        onClick={() => {
                                            setIniciandoId(servicio.id);
                                            setIniciarOpen(true);
                                        }}
                                    >
                                        <Play className="h-4 w-4" /> Iniciar
                                    </Button>
                                ) : null}
                                {canFinalizar &&
                                !finalizado &&
                                iniciado &&
                                servicio.tipo_servicio === 'MANTENIMIENTO' ? (
                                    <Button
                                        size="sm"
                                        className="!border-transparent !bg-blue-500 !text-white hover:!bg-blue-600"
                                        data-test="servicio-tercero-continuar-button"
                                        onClick={() => {
                                            setContinuandoId(servicio.id);
                                            setContinuarOpen(true);
                                        }}
                                    >
                                        <FastForward className="h-4 w-4" />{' '}
                                        Continuar
                                    </Button>
                                ) : null}
                                {canFinalizar &&
                                !finalizado &&
                                iniciado &&
                                servicio.tipo_servicio !== 'MANTENIMIENTO' ? (
                                    <Button
                                        size="sm"
                                        className="!border-transparent !bg-blue-500 !text-white hover:!bg-blue-600"
                                        data-test="servicio-tercero-finalizar-button"
                                        onClick={() => {
                                            setCodigosBorrador(null);
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
        [canEdit, canDelete, canIniciar, canFinalizar],
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
            <VerServicioTerceroModal
                servicio={viendo}
                open={verOpen}
                onOpenChange={setVerOpen}
            />
            <ContinuarServicioTerceroModal
                servicio={continuando}
                open={continuarOpen}
                onOpenChange={setContinuarOpen}
                onFinalizar={(codigos) => {
                    setCodigosBorrador(codigos);
                    setFinalizandoId(continuandoId);
                    setContinuarOpen(false);
                    setFinalizarOpen(true);
                }}
            />
            <FinalizarServicioTerceroModal
                servicio={finalizando}
                codigos={codigosBorrador}
                open={finalizarOpen}
                onOpenChange={setFinalizarOpen}
            />
        </>
    );
}
