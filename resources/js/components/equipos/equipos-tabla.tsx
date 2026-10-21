import { Eye, History, Pencil, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import DeleteEquipoModal from '@/components/equipos/delete-equipo-modal';
import EditEquipoModal from '@/components/equipos/edit-equipo-modal';
import { estadosVencimiento } from '@/components/equipos/equipo-programacion-fields';
import HistorialEquipoModal from '@/components/equipos/historial-equipo-modal';
import ViewEquipoModal from '@/components/equipos/view-equipo-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import {
    BADGE_VENCIMIENTO,
    vencimientoDelEquipo,
} from '@/lib/vencimiento-programacion';
import type { Equipo, EquipoFormOptions } from '@/types';

type Props = {
    equipos: Equipo[];
    options: EquipoFormOptions;
    /**
     * Estado de vencimiento con el que arranca filtrada la tabla (o `NINGUNO`); para
     * cambiarlo desde fuera, vuelve a montar la tabla con otra `key`.
     */
    vencimientoInicial?: string | null;
};

const columnHelper = createDataTableColumnHelper<Equipo>();

/** Gravedad con la que se ordena la columna de vencimiento: sin programación < al día < vencido. */
const GRAVEDAD: Record<string, number> = {
    NINGUNO: 0,
    AL_DIA: 1,
    PROXIMO_A_VENCER: 2,
    VENCIDO: 3,
};

/** Estado de vencimiento más urgente del equipo, o `NINGUNO` si no tiene programación pendiente. */
function estadoVencimientoDelEquipo(equipo: Equipo): string {
    return (
        vencimientoDelEquipo(equipo.equipo_programaciones)?.estado ?? 'NINGUNO'
    );
}

/** Vencimiento más urgente entre las programaciones por atender del equipo. */
function VencimientoEquipo({ equipo }: { equipo: Equipo }) {
    const vencimiento = vencimientoDelEquipo(equipo.equipo_programaciones);

    if (!vencimiento) {
        return <span className="text-muted-foreground">—</span>;
    }

    const etiqueta =
        estadosVencimiento.find((option) => option.value === vencimiento.estado)
            ?.label ?? vencimiento.estado;

    return (
        <div className="space-y-1" data-test="equipo-vencimiento">
            <Badge className={BADGE_VENCIMIENTO[vencimiento.estado]}>
                {etiqueta}
            </Badge>
            {vencimiento.fecha ? (
                <p className="text-xs text-muted-foreground">
                    Próximo servicio: {vencimiento.fecha}
                </p>
            ) : null}
        </div>
    );
}

/**
 * Tabla de equipos con búsqueda, filtros por columna, orden y paginación hechos en el
 * navegador sobre todos los equipos recibidos, sin pedir nada más al servidor.
 */
export default function EquiposTabla({
    equipos,
    options,
    vencimientoInicial = null,
}: Props) {
    const { can } = usePermissions();
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [equipoDeleting, setEquipoDeleting] = useState<Equipo | null>(null);
    const [historialOpen, setHistorialOpen] = useState(false);
    const [equipoHistorial, setEquipoHistorial] = useState<Equipo | null>(null);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('codigo', {
                    header: 'Código',
                    cell: (info) => (
                        <span className="font-medium">{info.getValue()}</span>
                    ),
                }),
                columnHelper.accessor('modelo', { header: 'Modelo' }),
                columnHelper.accessor((row) => row.tipo_equipo?.nombre ?? '', {
                    id: 'tipo_equipo',
                    header: 'Tipo de equipo',
                    cell: (info) => info.getValue() || '—',
                }),
                columnHelper.accessor((row) => row.fabricante?.nombre ?? '', {
                    id: 'fabricante',
                    header: 'Fabricante',
                    cell: (info) => info.getValue() || '—',
                }),
                columnHelper.accessor((row) => row.cliente?.nombre ?? '', {
                    id: 'cliente',
                    header: 'Cliente',
                    cell: (info) => info.getValue() || '—',
                }),
                columnHelper.accessor(estadoVencimientoDelEquipo, {
                    id: 'vencimiento',
                    header: 'Vencimiento',
                    meta: {
                        filterOptions: [
                            ...estadosVencimiento,
                            {
                                value: 'NINGUNO',
                                label: 'Sin programación pendiente',
                            },
                        ],
                    },
                    sortFn: (filaA, filaB, columnaId) =>
                        (GRAVEDAD[filaA.getValue(columnaId) as string] ?? 0) -
                        (GRAVEDAD[filaB.getValue(columnaId) as string] ?? 0),
                    cell: ({ row }) => (
                        <VencimientoEquipo equipo={row.original} />
                    ),
                }),
                columnHelper.accessor('activo', {
                    header: 'Estado',
                    meta: {
                        filterOptions: [
                            { value: 'true', label: 'Activo' },
                            { value: 'false', label: 'Inactivo' },
                        ],
                    },
                    cell: (info) => (
                        <Badge
                            variant={info.getValue() ? 'default' : 'secondary'}
                        >
                            {info.getValue() ? 'Activo' : 'Inactivo'}
                        </Badge>
                    ),
                }),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    enableColumnFilter: false,
                    cell: ({ row }) => (
                        <div className="flex items-center justify-end gap-2">
                            {can('equipos.ver') ? (
                                <ViewEquipoModal
                                    equipo={row.original}
                                    options={options}
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        aria-label="Ver equipo"
                                        data-test="equipo-view-button"
                                    >
                                        <Eye className="h-4 w-4" />
                                    </Button>
                                </ViewEquipoModal>
                            ) : null}
                            {can('equipos.historial') ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    aria-label="Historial del equipo"
                                    title="Historial"
                                    data-test="equipo-historial-button"
                                    onClick={() => {
                                        setEquipoHistorial(row.original);
                                        setHistorialOpen(true);
                                    }}
                                >
                                    <History className="h-4 w-4" />
                                </Button>
                            ) : null}
                            {can('equipos.editar') ? (
                                <EditEquipoModal
                                    equipo={row.original}
                                    options={options}
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        data-test="equipo-edit-button"
                                    >
                                        <Pencil className="h-4 w-4" />
                                    </Button>
                                </EditEquipoModal>
                            ) : null}
                            {can('equipos.eliminar') ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    data-test="equipo-delete-button"
                                    onClick={() => {
                                        setEquipoDeleting(row.original);
                                        setDeleteDialogOpen(true);
                                    }}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            ) : null}
                        </div>
                    ),
                }),
            ]),
        [can, options],
    );

    return (
        <>
            <DataTable
                data={equipos}
                columns={columns}
                pageSize={15}
                rowTestId="equipo-row"
                searchPlaceholder="Buscar equipos..."
                emptyMessage="Aún no has registrado equipos."
                initialColumnFilters={
                    vencimientoInicial
                        ? [{ id: 'vencimiento', value: vencimientoInicial }]
                        : undefined
                }
            />

            <DeleteEquipoModal
                equipo={equipoDeleting}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />

            <HistorialEquipoModal
                equipo={equipoHistorial}
                open={historialOpen}
                onOpenChange={setHistorialOpen}
            />
        </>
    );
}
