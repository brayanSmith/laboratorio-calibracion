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
import Heading from '@/components/heading';
import TarjetasEstado, {
    useFiltroPorTarjeta,
} from '@/components/tarjetas-estado';
import type { TarjetaEstado } from '@/components/tarjetas-estado';
import DeleteMantenimientoModal from '@/components/mantenimientos/delete-mantenimiento-modal';
import EditMantenimientoModal from '@/components/mantenimientos/edit-mantenimiento-modal';
import { informe } from '@/actions/App/Modules/Mantenimientos/Http/Controllers/MantenimientoController';
import GestionarMantenimientoModal from '@/components/mantenimientos/gestionar-mantenimiento-modal';
import IniciarMantenimientoModal from '@/components/mantenimientos/iniciar-mantenimiento-modal';
import VerMantenimientoModal from '@/components/mantenimientos/ver-mantenimiento-modal';
import ServicioTercerosTab from '@/components/servicio-terceros/servicio-terceros-tab';
import TabCountBadge from '@/components/tab-count-badge';
import PlazoServicioCelda from '@/components/plazo-servicio-celda';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/hooks/use-permissions';
import {
    estadoServicioMantenimiento,
    estadosServicio,
    opcionesEstadoServicio,
} from '@/lib/estado-servicio';
import {
    colorEstadoEquipoMantenimiento,
    estadosEquipoMantenimiento,
} from '@/lib/estados-mantenimiento';
import { index } from '@/routes/mantenimientos';
import type {
    Mantenimiento,
    MantenimientoOption,
    MantenimientoOptions,
    ServicioTerceroListado,
} from '@/types';
import {
    CeldaEquipo,
    textoEquipo,
} from '@/components/equipos/celdas-equipo';

/** Convierte un ISO string a Date, o null si no hay nada que convertir. */
function toDateOrNull(value: string | null): Date | null {
    return value ? new Date(value) : null;
}

type Props = MantenimientoOptions & {
    mantenimientos: Mantenimiento[];
    serviciosTerceros: ServicioTerceroListado[];
    empresasTerceras: MantenimientoOption[];
};

const columnHelper = createDataTableColumnHelper<Mantenimiento>();
const tiposMantenimientoLabel: Record<string, string> = {
    PREVENTIVO: 'Preventivo',
    CORRECTIVO: 'Correctivo',
};

export default function MantenimientosIndex({
    mantenimientos,
    serviciosTerceros,
    empresasTerceras,
    tecnicos,
    novedadesMantenimiento,
    items,
}: Props) {
    const { can } = usePermissions();
    const canEdit = can('mantenimientos.editar');
    const canDelete = can('mantenimientos.eliminar');
    const canIniciar = can('mantenimientos.iniciar');
    const canPdf = can('mantenimientos.pdf');
    const canTerceros = can('mantenimientos.terceros');
    const [editingId, setEditingId] = useState<number | null>(null);
    const editing =
        mantenimientos.find(
            (mantenimiento) => mantenimiento.id === editingId,
        ) ?? null;
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<Mantenimiento | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [iniciandoId, setIniciandoId] = useState<number | null>(null);
    const iniciando =
        mantenimientos.find(
            (mantenimiento) => mantenimiento.id === iniciandoId,
        ) ?? null;
    const [iniciarOpen, setIniciarOpen] = useState(false);
    const [gestionandoId, setGestionandoId] = useState<number | null>(null);
    const gestionando =
        mantenimientos.find(
            (mantenimiento) => mantenimiento.id === gestionandoId,
        ) ?? null;
    const [gestionarOpen, setGestionarOpen] = useState(false);
    const [iniciadoEn, setIniciadoEn] = useState<Date | null>(null);
    const [viendoId, setViendoId] = useState<number | null>(null);
    const viendo =
        mantenimientos.find((mantenimiento) => mantenimiento.id === viendoId) ??
        null;
    const [verOpen, setVerOpen] = useState(false);
    const { claveTabla, filtrosIniciales, tarjetaActiva, ver } =
        useFiltroPorTarjeta();

    const contar = (
        cumple: (mantenimiento: Mantenimiento) => boolean,
    ): number => mantenimientos.filter(cumple).length;
    const faltaRepuestos = contar(
        (m) => m.estado_mantenimiento === 'FALTA_REPUESTOS',
    );
    const tarjetas: TarjetaEstado[] = [
        {
            id: 'mantenimientos-todos',
            titulo: 'Todos',
            valor: mantenimientos.length,
            icono: Boxes,
            tono: 'sky',
            filtros: [],
        },
        {
            id: 'mantenimientos-pendientes',
            titulo: 'Pendientes',
            valor: contar((m) => m.estado_mantenimiento === 'PENDIENTE'),
            icono: Clock,
            tono: 'amber',
            detalle: 'Sin iniciar',
            filtros: [{ id: 'estado', value: 'PENDIENTE' }],
        },
        {
            id: 'mantenimientos-en-proceso',
            titulo: 'En proceso',
            valor: contar(
                (m) =>
                    estadoServicioMantenimiento(m.estado_mantenimiento) ===
                    'EN_PROCESO',
            ),
            icono: Loader,
            tono: 'blue',
            detalle:
                faltaRepuestos > 0
                    ? `${faltaRepuestos} con falta de repuestos`
                    : undefined,
            filtros: [{ id: 'estado', value: 'EN_PROCESO' }],
        },
        {
            id: 'mantenimientos-finalizados',
            titulo: 'Finalizados',
            valor: contar((m) => m.estado_mantenimiento === 'FINALIZADO'),
            icono: CheckCircle2,
            tono: 'emerald',
            filtros: [{ id: 'estado', value: 'FINALIZADO' }],
        },
        {
            id: 'mantenimientos-fuera-de-servicio',
            titulo: 'Fuera de servicio',
            valor: contar((m) => m.estado_final_equipo === 'FUERA_DE_SERVICIO'),
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
                columnHelper.accessor(
                    (row) => row.equipo.cliente?.nombre ?? '',
                    {
                        id: 'cliente',
                        header: 'Cliente',
                        cell: ({ row }) =>
                            row.original.equipo.cliente?.nombre ?? '—',
                    },
                ),
                columnHelper.accessor('tipo_mantenimiento', {
                    meta: {
                        filterOptions: Object.entries(
                            tiposMantenimientoLabel,
                        ).map(([value, label]) => ({ value, label })),
                    },
                    header: 'Tipo',
                    cell: (info) =>
                        tiposMantenimientoLabel[info.getValue()] ??
                        info.getValue(),
                }),
                columnHelper.accessor('tecnico_nombre', { header: 'Técnico' }),
                columnHelper.accessor(
                    (row) =>
                        estadoServicioMantenimiento(row.estado_mantenimiento),
                    {
                        id: 'estado',
                        meta: { filterOptions: opcionesEstadoServicio },
                        header: 'Estado',
                        cell: ({ row, getValue }) => (
                            <div className="space-y-1">
                                <Badge
                                    className={
                                        estadosServicio[getValue()].className
                                    }
                                >
                                    {estadosServicio[getValue()].label}
                                </Badge>
                                {/* "Falta repuestos" cuenta como en proceso, pero se sigue indicando. */}
                                {row.original.estado_mantenimiento ===
                                'FALTA_REPUESTOS' ? (
                                    <p className="text-xs text-muted-foreground">
                                        Falta repuestos
                                    </p>
                                ) : null}
                            </div>
                        ),
                    },
                ),
                columnHelper.accessor('estado_final_equipo', {
                    meta: {
                        filterOptions: [
                            ...estadosEquipoMantenimiento,
                            { value: 'null', label: 'Sin finalizar' },
                        ],
                    },
                    header: 'Estado final del equipo',
                    cell: (info) => {
                        const estadoFinal = info.getValue();

                        return estadoFinal ? (
                            <Badge
                                className={
                                    colorEstadoEquipoMantenimiento[estadoFinal]
                                }
                                data-test="mantenimiento-estado-final-equipo"
                            >
                                {estadosEquipoMantenimiento.find(
                                    (estado) => estado.value === estadoFinal,
                                )?.label ?? estadoFinal}
                            </Badge>
                        ) : (
                            <span className="text-muted-foreground">—</span>
                        );
                    },
                }),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    cell: ({ row }) => (
                        <div className="flex items-center justify-end gap-2">
                            {row.original.estado_mantenimiento ===
                            'FINALIZADO' ? (
                                <BotonAccion
                                    icono={Eye}
                                    etiqueta="Ver"
                                    variant="outline"
                                    data-test="mantenimiento-ver-button"
                                    onClick={() => {
                                        setViendoId(row.original.id);
                                        setVerOpen(true);
                                    }}
                                />
                            ) : null}
                            {canPdf &&
                            row.original.estado_mantenimiento ===
                                'FINALIZADO' ? (
                                <BotonAccion
                                    icono={Printer}
                                    etiqueta="Informe PDF"
                                    variant="outline"
                                    href={informe.url(row.original.id)}
                                    target="_blank"
                                    data-test="mantenimiento-imprimir-button"
                                />
                            ) : null}
                            {canEdit &&
                            row.original.estado_mantenimiento !==
                                'FINALIZADO' &&
                            row.original.tiempo_servicio_inicio ? (
                                <BotonAccion
                                    icono={Play}
                                    etiqueta="Continuar"
                                    variant="outline"
                                    data-test="mantenimiento-continuar-button"
                                    onClick={() => {
                                        setGestionandoId(row.original.id);
                                        setIniciadoEn(
                                            toDateOrNull(
                                                row.original
                                                    .tiempo_servicio_inicio,
                                            ),
                                        );
                                        setGestionarOpen(true);
                                    }}
                                />
                            ) : null}
                            {canIniciar &&
                            row.original.estado_mantenimiento !==
                                'FINALIZADO' &&
                            !row.original.tiempo_servicio_inicio ? (
                                <BotonAccion
                                    icono={Play}
                                    etiqueta="Iniciar"
                                    className="!border-transparent !bg-emerald-600 !text-white hover:!bg-emerald-700"
                                    data-test="mantenimiento-iniciar-button"
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
                                    data-test="mantenimiento-edit-button"
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
                                    data-test="mantenimiento-delete-button"
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
            <Head title="Mantenimientos" />

            <h1 className="sr-only">Mantenimientos</h1>

            <div className="flex flex-col space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Mantenimientos"
                    description="Mantenimientos agendados desde Ingresos, con su técnico, estado y novedad"
                />

                <Tabs defaultValue="local">
                    <TabsList>
                        <TabsTrigger value="local" className="gap-1.5">
                            <FlaskConical className="h-4 w-4" />
                            Local
                            <TabCountBadge
                                value={
                                    mantenimientos.filter(
                                        (mantenimiento) =>
                                            mantenimiento.estado_mantenimiento !==
                                            'FINALIZADO',
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
                            data={mantenimientos}
                            columns={columns}
                            searchPlaceholder="Buscar mantenimiento..."
                            emptyMessage="Aún no hay mantenimientos agendados."
                            rowTestId="mantenimiento-row"
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
                                canFinalizar={can('mantenimientos.finalizar')}
                                searchPlaceholder="Buscar servicio de tercero..."
                                emptyMessage="Aún no hay mantenimientos asignados a terceros."
                            />
                        </TabsContent>
                    ) : null}
                </Tabs>
            </div>

            <EditMantenimientoModal
                mantenimiento={editing}
                tecnicos={tecnicos}
                novedadesMantenimiento={novedadesMantenimiento}
                open={editOpen}
                onOpenChange={setEditOpen}
            />
            <DeleteMantenimientoModal
                mantenimiento={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
            <IniciarMantenimientoModal
                mantenimiento={iniciando}
                open={iniciarOpen}
                onOpenChange={setIniciarOpen}
                onIniciado={() => {
                    setIniciadoEn(new Date());
                    setGestionandoId(iniciandoId);
                    setGestionarOpen(true);
                }}
            />
            <VerMantenimientoModal
                mantenimiento={viendo}
                open={verOpen}
                onOpenChange={setVerOpen}
            />
            <GestionarMantenimientoModal
                mantenimiento={gestionando}
                items={items}
                iniciadoEn={iniciadoEn}
                open={gestionarOpen}
                onOpenChange={setGestionarOpen}
            />
        </>
    );
}

MantenimientosIndex.layout = {
    breadcrumbs: [
        {
            title: 'Mantenimientos',
            href: index(),
        },
    ],
};
