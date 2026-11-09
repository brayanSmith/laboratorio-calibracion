import { Head } from '@inertiajs/react';
import { LayoutGrid, Pencil, Plus, Trash2, UserRound } from 'lucide-react';
import { useMemo, useState } from 'react';
import BotonAccion from '@/components/boton-accion';
import DataTable, {
    createDataTableColumnHelper,
} from '@/components/data-table';
import Heading from '@/components/heading';
import EliminarObjetivoUsuarioModal from '@/components/objetivos/eliminar-objetivo-usuario-modal';
import ObjetivoUsuarioModal from '@/components/objetivos/objetivo-usuario-modal';
import ObjetivosDashboardPanel from '@/components/objetivos/objetivos-dashboard-panel';
import { TIPOS_OBJETIVO_USUARIO } from '@/components/objetivos/tipos';
import type {
    ObjetivosDelTipo,
    ObjetivoUsuario,
    Opcion,
} from '@/components/objetivos/tipos';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { index } from '@/routes/objetivos';

type Props = {
    objetivos: ObjetivoUsuario[];
    usuarios: Opcion[];
    tiposEquipo: Opcion[];
};

const columnHelper = createDataTableColumnHelper<ObjetivoUsuario>();

const formatoObjetivo = new Intl.NumberFormat('es', {
    maximumFractionDigits: 2,
});

/** "10 cada mes" si todos los meses tienen lo mismo; si no, el rango y cuántos meses. */
function resumenMensual(objetivos: ObjetivosDelTipo): string {
    const valores = Object.values(objetivos.meses).filter(
        (valor): valor is number => valor !== undefined,
    );
    const minimo = Math.min(...valores);
    const maximo = Math.max(...valores);
    const meses = valores.length === 12 ? '' : ` · ${valores.length} meses`;

    return minimo === maximo
        ? `${formatoObjetivo.format(minimo)} cada mes${meses}`
        : `${formatoObjetivo.format(minimo)} a ${formatoObjetivo.format(maximo)}${meses}`;
}

/** Objetivos de un tipo en la tabla: el resumen por mes y el total del año. */
function ResumenTipo({ objetivos }: { objetivos: ObjetivosDelTipo | null }) {
    if (objetivos === null) {
        return <span className="text-muted-foreground">Sin objetivo</span>;
    }

    return (
        <div className="grid">
            <span>{resumenMensual(objetivos)}</span>
            <span className="text-xs text-muted-foreground">
                {formatoObjetivo.format(objetivos.total)} en el año
            </span>
        </div>
    );
}

export default function ObjetivosIndex({
    objetivos,
    usuarios,
    tiposEquipo,
}: Props) {
    // Al crear queda en null; el modal se abre con "formularioAbierto".
    const [editando, setEditando] = useState<ObjetivoUsuario | null>(null);
    const [formularioAbierto, setFormularioAbierto] = useState(false);
    const [eliminando, setEliminando] = useState<ObjetivoUsuario | null>(null);
    const [eliminarAbierto, setEliminarAbierto] = useState(false);

    const columns = useMemo(
        () =>
            columnHelper.columns([
                columnHelper.accessor('usuario', {
                    header: 'Usuario',
                    cell: (info) => (
                        <span className="font-medium">{info.getValue()}</span>
                    ),
                }),
                columnHelper.accessor('anio', { header: 'Año' }),
                ...TIPOS_OBJETIVO_USUARIO.map((tipo) =>
                    columnHelper.accessor(
                        (fila) => fila.tipos[tipo.value]?.total ?? null,
                        {
                            id: tipo.value.toLowerCase(),
                            header: tipo.label,
                            cell: ({ row }) => (
                                <ResumenTipo
                                    objetivos={row.original.tipos[tipo.value]}
                                />
                            ),
                        },
                    ),
                ),
                columnHelper.display({
                    id: 'acciones',
                    header: '',
                    enableSorting: false,
                    cell: ({ row }) => (
                        <div className="flex items-center justify-end gap-2">
                            <BotonAccion
                                icono={Pencil}
                                etiqueta="Editar"
                                data-test="objetivo-usuario-edit-button"
                                onClick={() => {
                                    setEditando(row.original);
                                    setFormularioAbierto(true);
                                }}
                            />
                            <BotonAccion
                                icono={Trash2}
                                etiqueta="Eliminar"
                                data-test="objetivo-usuario-delete-button"
                                onClick={() => {
                                    setEliminando(row.original);
                                    setEliminarAbierto(true);
                                }}
                            />
                        </div>
                    ),
                }),
            ]),
        [],
    );

    return (
        <>
            <Head title="Objetivos" />

            <h1 className="sr-only">Objetivos</h1>

            <div className="flex flex-col space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Objetivos"
                    description="Define las metas de cada usuario y los objetivos de los indicadores del dashboard"
                />

                <Tabs defaultValue="usuarios">
                    <TabsList>
                        <TabsTrigger value="usuarios" className="gap-1.5">
                            <UserRound className="h-4 w-4" />
                            Por usuario
                        </TabsTrigger>
                        <TabsTrigger value="dashboard" className="gap-1.5">
                            <LayoutGrid className="h-4 w-4" />
                            Dashboard
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="usuarios" className="space-y-4">
                        <div className="flex justify-end">
                            <Button
                                data-test="objetivos-usuario-new-button"
                                onClick={() => {
                                    setEditando(null);
                                    setFormularioAbierto(true);
                                }}
                            >
                                <Plus /> Nuevo objetivo
                            </Button>
                        </div>

                        <DataTable
                            data={objetivos}
                            columns={columns}
                            searchPlaceholder="Buscar usuario..."
                            emptyMessage="Aún no has definido objetivos por usuario."
                            rowTestId="objetivo-usuario-row"
                        />
                    </TabsContent>

                    <TabsContent value="dashboard">
                        <ObjetivosDashboardPanel tiposEquipo={tiposEquipo} />
                    </TabsContent>
                </Tabs>
            </div>

            <ObjetivoUsuarioModal
                objetivo={editando}
                usuarios={usuarios}
                open={formularioAbierto}
                onOpenChange={setFormularioAbierto}
            />
            <EliminarObjetivoUsuarioModal
                objetivo={eliminando}
                open={eliminarAbierto}
                onOpenChange={setEliminarAbierto}
            />
        </>
    );
}

ObjetivosIndex.layout = {
    breadcrumbs: [
        {
            title: 'Objetivos',
            href: index(),
        },
    ],
};
