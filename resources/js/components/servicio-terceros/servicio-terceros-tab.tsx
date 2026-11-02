import { CheckCircle2, Eye, Pencil, Play, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import BotonAccion from '@/components/boton-accion';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import DeleteServicioTerceroModal from '@/components/servicio-terceros/delete-servicio-tercero-modal';
import FinalizarServicioTerceroModal from '@/components/servicio-terceros/finalizar-servicio-tercero-modal';
import IniciarServicioTerceroModal from '@/components/servicio-terceros/iniciar-servicio-tercero-modal';
import ServicioTerceroModal from '@/components/servicio-terceros/servicio-tercero-modal';
import TiempoTranscurrido from '@/components/servicio-terceros/tiempo-transcurrido';
import VerServicioTerceroModal from '@/components/servicio-terceros/ver-servicio-tercero-modal';
import PlazoServicioCelda from '@/components/plazo-servicio-celda';
import { Badge } from '@/components/ui/badge';
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
    const [viendoId, setViendoId] = useState<number | null>(null);
    const viendo =
        servicios.find((servicio) => servicio.id === viendoId) ?? null;
    const [verOpen, setVerOpen] = useState(false);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('fecha_programada', {
                    header: 'Programado',
                    cell: ({ row }) => (
                        <PlazoServicioCelda
                            fechaProgramada={row.original.fecha_programada}
                            fechaRealizacion={row.original.fecha_realizacion}
                        />
                    ),
                }),
                columnHelper.accessor('orden_trabajo_codigo', {
                    header: 'Orden de trabajo',
                }),
                columnHelper.accessor('fecha_programada', {
                    header: 'Programado',
                    cell: ({ row }) => (
                        <PlazoServicioCelda
                            fechaProgramada={row.original.fecha_programada}
                            fechaRealizacion={row.original.fecha_realizacion}
                        />
                    ),
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
                                    <BotonAccion
                                        icono={Eye}
                                        etiqueta="Ver"
                                        variant="outline"
                                        data-test="servicio-tercero-ver-button"
                                        onClick={() => {
                                            setViendoId(servicio.id);
                                            setVerOpen(true);
                                        }}
                                    />
                                ) : null}
                                {canIniciar && !finalizado && !iniciado ? (
                                    <BotonAccion
                                        icono={Play}
                                        etiqueta="Iniciar"
                                        className="!border-transparent !bg-emerald-600 !text-white hover:!bg-emerald-700"
                                        data-test="servicio-tercero-iniciar-button"
                                        onClick={() => {
                                            setIniciandoId(servicio.id);
                                            setIniciarOpen(true);
                                        }}
                                    />
                                ) : null}
                                {canFinalizar && !finalizado && iniciado ? (
                                    <BotonAccion
                                        icono={CheckCircle2}
                                        etiqueta="Finalizar"
                                        className="!border-transparent !bg-blue-500 !text-white hover:!bg-blue-600"
                                        data-test="servicio-tercero-finalizar-button"
                                        onClick={() => {
                                            setFinalizandoId(servicio.id);
                                            setFinalizarOpen(true);
                                        }}
                                    />
                                ) : null}
                                {canEdit ? (
                                    <BotonAccion
                                        icono={Pencil}
                                        etiqueta="Editar"
                                        data-test="servicio-tercero-edit-button"
                                        onClick={() => {
                                            setEditId(servicio.id);
                                            setEditOpen(true);
                                        }}
                                    />
                                ) : null}
                                {canDelete ? (
                                    <BotonAccion
                                        icono={Trash2}
                                        etiqueta="Eliminar"
                                        data-test="servicio-tercero-delete-button"
                                        onClick={() => {
                                            setDeleting(servicio);
                                            setDeleteOpen(true);
                                        }}
                                    />
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
            <FinalizarServicioTerceroModal
                servicio={finalizando}
                open={finalizarOpen}
                onOpenChange={setFinalizarOpen}
            />
        </>
    );
}
