import { Head } from '@inertiajs/react';
import {
    Boxes,
    CheckCircle2,
    Clock,
    Eye,
    FlaskConical,
    Handshake,
    Loader,
    Pencil,
    Play,
    Printer,
    ShieldAlert,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import BotonAccion from '@/components/boton-accion';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import { certificado } from '@/actions/App/Modules/Calibraciones/Http/Controllers/CalibracionController';
import DeleteCalibracionModal from '@/components/calibraciones/delete-calibracion-modal';
import EditCalibracionModal from '@/components/calibraciones/edit-calibracion-modal';
import IniciarCalibracionModal from '@/components/calibraciones/iniciar-calibracion-modal';
import VerCalibracionModal from '@/components/calibraciones/ver-calibracion-modal';
import Heading from '@/components/heading';
import TarjetasEstado, {
    useFiltroPorTarjeta,
} from '@/components/tarjetas-estado';
import type { TarjetaEstado } from '@/components/tarjetas-estado';
import ServicioTercerosTab from '@/components/servicio-terceros/servicio-terceros-tab';
import TabCountBadge from '@/components/tab-count-badge';
import PlazoServicioCelda from '@/components/plazo-servicio-celda';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/hooks/use-permissions';
import {
    estadoFinalEquipoCalibracion,
    estadosFinalesEquipoCalibracion,
} from '@/lib/estado-final-equipo';
import {
    estadoServicioCalibracion,
    estadosServicio,
    opcionesEstadoServicio,
} from '@/lib/estado-servicio';
import { index } from '@/routes/calibraciones';
import type {
    Calibracion,
    CalibracionOption,
    CalibracionOptions,
    ServicioTerceroListado,
} from '@/types';

/** Convierte un ISO string a Date, o null si no hay nada que convertir. */
function toDateOrNull(value: string | null): Date | null {
    return value ? new Date(value) : null;
}

type Props = CalibracionOptions & {
    calibraciones: Calibracion[];
    serviciosTerceros: ServicioTerceroListado[];
    empresasTerceras: CalibracionOption[];
};

const columnHelper = createDataTableColumnHelper<Calibracion>();

/** Una calibración finalizada, devuelta a mantenimiento o fuera de servicio ya se realizó: no se vuelve a iniciar. */
function terminada(calibracion: Calibracion): boolean {
    return (
        calibracion.estado_calibracion === 'FINALIZADO' ||
        calibracion.estado_calibracion === 'DEVOLVER_MANTENIMIENTO' ||
        calibracion.estado_calibracion === 'FUERA_DE_SERVICIO'
    );
}

export default function CalibracionesIndex({
    calibraciones,
    serviciosTerceros,
    empresasTerceras,
    laboratorioPropio,
    procedimientos,
    novedadesCalibracion,
}: Props) {
    const { can } = usePermissions();
    const canEdit = can('calibraciones.editar');
    const canDelete = can('calibraciones.eliminar');
    const canIniciar = can('calibraciones.iniciar');
    const canPdf = can('calibraciones.pdf');
    const canTerceros = can('calibraciones.terceros');
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing =
        calibraciones.find((calibracion) => calibracion.id === editingId) ??
        null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<Calibracion | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [iniciandoId, setIniciandoId] = useState<number | null>(null);
    const iniciando =
        calibraciones.find((calibracion) => calibracion.id === iniciandoId) ??
        null;
    const [iniciarOpen, setIniciarOpen] = useState(false);
    const [iniciadoEn, setIniciadoEn] = useState<Date | null>(null);
    const [viendoId, setViendoId] = useState<number | null>(null);
    const viendo =
        calibraciones.find((calibracion) => calibracion.id === viendoId) ??
        null;
    const [verOpen, setVerOpen] = useState(false);
    const { claveTabla, filtrosIniciales, tarjetaActiva, ver } =
        useFiltroPorTarjeta();

    const contar = (cumple: (calibracion: Calibracion) => boolean): number =>
        calibraciones.filter(cumple).length;
    const devueltas = contar(
        (c) => c.estado_calibracion === 'DEVOLVER_MANTENIMIENTO',
    );
    const tarjetas: TarjetaEstado[] = [
        {
            id: 'calibraciones-todas',
            titulo: 'Todas',
            valor: calibraciones.length,
            icono: Boxes,
            tono: 'sky',
            filtros: [],
        },
        {
            id: 'calibraciones-pendientes',
            titulo: 'Pendientes',
            valor: contar((c) => c.estado_calibracion === 'PENDIENTE'),
            icono: Clock,
            tono: 'amber',
            detalle: 'Sin iniciar',
            filtros: [{ id: 'estado', value: 'PENDIENTE' }],
        },
        {
            id: 'calibraciones-en-proceso',
            titulo: 'En proceso',
            valor: contar((c) => c.estado_calibracion === 'EN_PROCESO'),
            icono: Loader,
            tono: 'blue',
            filtros: [{ id: 'estado', value: 'EN_PROCESO' }],
        },
        {
            id: 'calibraciones-finalizadas',
            titulo: 'Finalizadas',
            valor: contar(
                (c) =>
                    estadoServicioCalibracion(c.estado_calibracion) ===
                    'FINALIZADO',
            ),
            icono: CheckCircle2,
            tono: 'emerald',
            detalle:
                devueltas > 0
                    ? `${devueltas} devueltas a mantenimiento`
                    : undefined,
            filtros: [{ id: 'estado', value: 'FINALIZADO' }],
        },
        {
            id: 'calibraciones-fuera-de-servicio',
            titulo: 'Fuera de servicio',
            valor: contar((c) => c.estado_calibracion === 'FUERA_DE_SERVICIO'),
            icono: ShieldAlert,
            tono: 'red',
            detalle: 'Equipos que terminaron fuera de servicio',
            filtros: [
                { id: 'estado_final_equipo', value: 'FUERA_DE_SERVICIO' },
            ],
        },
    ];

    const columns = useMemo(
        () =>
            columnHelper.columns([
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
                    (row) => `${row.equipo.codigo} ${row.equipo.modelo}`,
                    {
                        id: 'equipo',
                        header: 'Equipo',
                        cell: ({ row }) => (
                            <span className="font-medium">
                                {row.original.equipo.codigo} ·{' '}
                                {row.original.equipo.modelo}
                            </span>
                        ),
                    },
                ),
                columnHelper.accessor(
                    (row) => row.equipo.cliente?.nombre ?? '',
                    {
                        id: 'cliente',
                        header: 'Cliente',
                        cell: ({ row }) =>
                            row.original.equipo.cliente?.nombre ?? '—',
                    },
                ),
                columnHelper.accessor('tecnico_nombre', { header: 'Técnico' }),
                columnHelper.accessor('laboratorio_nombre', {
                    header: 'Laboratorio',
                    cell: (info) => info.getValue() ?? '—',
                }),
                columnHelper.accessor(
                    (row) => estadoServicioCalibracion(row.estado_calibracion),
                    {
                        id: 'estado',
                        meta: { filterOptions: opcionesEstadoServicio },
                        header: 'Estado',
                        cell: (info) => (
                            <Badge
                                className={
                                    estadosServicio[info.getValue()].className
                                }
                            >
                                {estadosServicio[info.getValue()].label}
                            </Badge>
                        ),
                    },
                ),
                columnHelper.accessor(
                    (row) => estadoFinalEquipoCalibracion(row) ?? 'null',
                    {
                        id: 'estado_final_equipo',
                        meta: {
                            filterOptions: [
                                ...Object.entries(
                                    estadosFinalesEquipoCalibracion,
                                ).map(([value, { label }]) => ({
                                    value,
                                    label,
                                })),
                                { value: 'null', label: 'Sin finalizar' },
                            ],
                        },
                        header: 'Estado final del equipo',
                        cell: ({ row }) => {
                            const estadoFinal = estadoFinalEquipoCalibracion(
                                row.original,
                            );

                            return estadoFinal ? (
                                <Badge
                                    className={
                                        estadosFinalesEquipoCalibracion[
                                            estadoFinal
                                        ].className
                                    }
                                    data-test="calibracion-estado-final-equipo"
                                >
                                    {
                                        estadosFinalesEquipoCalibracion[
                                            estadoFinal
                                        ].label
                                    }
                                </Badge>
                            ) : (
                                <span className="text-muted-foreground">—</span>
                            );
                        },
                    },
                ),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    cell: ({ row }) => (
                        <div className="flex items-center justify-end gap-2">
                            {terminada(row.original) ? (
                                <BotonAccion
                                    icono={Eye}
                                    etiqueta="Ver"
                                    variant="outline"
                                    data-test="calibracion-ver-button"
                                    onClick={() => {
                                        setViendoId(row.original.id);
                                        setVerOpen(true);
                                    }}
                                />
                            ) : null}
                            {canPdf &&
                            row.original.estado_calibracion === 'FINALIZADO' ? (
                                <BotonAccion
                                    icono={Printer}
                                    etiqueta="Certificado PDF"
                                    variant="outline"
                                    href={certificado.url(row.original.id)}
                                    target="_blank"
                                    data-test="calibracion-imprimir-button"
                                />
                            ) : null}
                            {canEdit &&
                            !terminada(row.original) &&
                            row.original.tiempo_servicio_inicio ? (
                                <BotonAccion
                                    icono={Play}
                                    etiqueta="Continuar"
                                    variant="outline"
                                    data-test="calibracion-continuar-button"
                                    onClick={() => {
                                        setEditingId(row.original.id);
                                        setIniciadoEn(
                                            toDateOrNull(
                                                row.original
                                                    .tiempo_servicio_inicio,
                                            ),
                                        );
                                        setEditOpen(true);
                                    }}
                                />
                            ) : null}
                            {canIniciar &&
                            !terminada(row.original) &&
                            !row.original.tiempo_servicio_inicio ? (
                                <BotonAccion
                                    icono={Play}
                                    etiqueta="Iniciar"
                                    className="!border-transparent !bg-emerald-600 !text-white hover:!bg-emerald-700"
                                    data-test="calibracion-iniciar-button"
                                    onClick={() => {
                                        setIniciandoId(row.original.id);
                                        setIniciarOpen(true);
                                    }}
                                />
                            ) : null}
                            {canEdit ? (
                                <BotonAccion
                                    icono={Pencil}
                                    etiqueta="Editar"
                                    data-test="calibracion-edit-button"
                                    onClick={() => {
                                        setEditingId(row.original.id);
                                        setIniciadoEn(
                                            toDateOrNull(
                                                row.original
                                                    .tiempo_servicio_inicio,
                                            ),
                                        );
                                        setEditOpen(true);
                                    }}
                                />
                            ) : null}
                            {canDelete ? (
                                <BotonAccion
                                    icono={Trash2}
                                    etiqueta="Eliminar"
                                    data-test="calibracion-delete-button"
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
        [canEdit, canDelete, canIniciar, canPdf],
    );

    return (
        <>
            <Head title="Calibraciones" />

            <h1 className="sr-only">Calibraciones</h1>

            <div className="flex flex-col space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Calibraciones"
                    description="Calibraciones agendadas desde Ingresos, con su técnico, estado y laboratorio"
                />

                <Tabs defaultValue="local">
                    <TabsList>
                        <TabsTrigger value="local" className="gap-1.5">
                            <FlaskConical className="h-4 w-4" />
                            Local
                            <TabCountBadge
                                value={
                                    calibraciones.filter(
                                        (calibracion) =>
                                            calibracion.estado_calibracion ===
                                                'PENDIENTE' ||
                                            calibracion.estado_calibracion ===
                                                'EN_PROCESO',
                                    ).length
                                }
                                label="Pendientes en local"
                                className="bg-red-500 text-white"
                            />
                        </TabsTrigger>
                        {canTerceros ? (
                            <TabsTrigger value="tercero" className="gap-1.5">
                                <Handshake className="h-4 w-4" />
                                Tercero
                                <TabCountBadge
                                    value={
                                        serviciosTerceros.filter(
                                            (servicio) =>
                                                servicio.estado_final_equipo ===
                                                null,
                                        ).length
                                    }
                                    label="Pendientes en tercero"
                                    className="bg-red-500 text-white"
                                />
                            </TabsTrigger>
                        ) : null}
                    </TabsList>

                    <TabsContent value="local" className="space-y-4">
                        <TarjetasEstado
                            tarjetas={tarjetas}
                            activa={tarjetaActiva}
                            onVer={ver}
                        />
                        <DataTable
                            key={claveTabla}
                            initialColumnFilters={filtrosIniciales}
                            data={calibraciones}
                            columns={columns}
                            searchPlaceholder="Buscar calibración..."
                            emptyMessage="Aún no hay calibraciones agendadas."
                            rowTestId="calibracion-row"
                        />
                    </TabsContent>

                    {canTerceros ? (
                        <TabsContent value="tercero">
                            <ServicioTercerosTab
                                servicios={serviciosTerceros}
                                empresasTerceras={empresasTerceras}
                                canEdit={canEdit}
                                canDelete={canDelete}
                                canIniciar={canIniciar}
                                canFinalizar={can('calibraciones.finalizar')}
                                searchPlaceholder="Buscar servicio de tercero..."
                                emptyMessage="Aún no hay calibraciones asignadas a terceros."
                            />
                        </TabsContent>
                    ) : null}
                </Tabs>
            </div>

            <EditCalibracionModal
                calibracion={editing}
                laboratorioPropio={laboratorioPropio}
                procedimientos={procedimientos}
                novedadesCalibracion={novedadesCalibracion}
                iniciadoEn={iniciadoEn}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteCalibracionModal
                calibracion={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
            <VerCalibracionModal
                calibracion={viendo}
                open={verOpen}
                onOpenChange={setVerOpen}
            />
            <IniciarCalibracionModal
                calibracion={iniciando}
                open={iniciarOpen}
                onOpenChange={setIniciarOpen}
            />
        </>
    );
}

CalibracionesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Calibraciones',
            href: index(),
        },
    ],
};
