import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CreateEquipoModal from '@/components/equipos/create-equipo-modal';
import DeleteEquipoModal from '@/components/equipos/delete-equipo-modal';
import EditEquipoModal from '@/components/equipos/edit-equipo-modal';
import Heading from '@/components/heading';
import ThRedimensionable from '@/components/th-redimensionable';
import { ColgroupAnchos } from '@/components/column-resize-handle';
import { useResizableTable } from '@/hooks/use-resizable-table';
import { cn } from '@/lib/utils';
import {
    FilaFiltrosServidor,
    LimpiarFiltrosServidor,
} from '@/components/filas-filtro-servidor';
import type { ColumnaFiltro } from '@/components/filas-filtro-servidor';
import { Badge } from '@/components/ui/badge';
import { estadosVencimiento } from '@/components/equipos/equipo-programacion-fields';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import {
    BADGE_VENCIMIENTO,
    vencimientoDelEquipo,
} from '@/lib/vencimiento-programacion';
import { index } from '@/routes/equipos';
import type { Equipo, EquipoFormOptions, EquipoPaginator } from '@/types';

type Props = {
    equipos: EquipoPaginator;
    options: EquipoFormOptions;
    /** Filtros por columna que el servidor aplicó a la lista. */
    filtros: Record<string, string>;
};

const CLAVES_COLUMNAS = [
    'codigo',
    'modelo',
    'tipo_equipo',
    'fabricante',
    'cliente',
    'vencimiento',
    'activo',
    'acciones',
];

const COLUMNAS_FILTRO: ColumnaFiltro[] = [
    { clave: 'codigo', etiqueta: 'Código' },
    { clave: 'modelo', etiqueta: 'Modelo' },
    { clave: 'tipo_equipo', etiqueta: 'Tipo de equipo' },
    { clave: 'fabricante', etiqueta: 'Fabricante' },
    { clave: 'cliente', etiqueta: 'Cliente' },
    {
        clave: 'vencimiento',
        etiqueta: 'Vencimiento',
        opciones: [
            ...estadosVencimiento,
            { value: 'NINGUNO', label: 'Sin programación pendiente' },
        ],
    },
    {
        clave: 'activo',
        etiqueta: 'Estado',
        opciones: [
            { value: '1', label: 'Activo' },
            { value: '0', label: 'Inactivo' },
        ],
    },
    null,
];

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
        <div className="space-y-1">
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

export default function EquiposIndex({ equipos, options, filtros }: Props) {
    const redimension = useResizableTable(CLAVES_COLUMNAS);
    const { can } = usePermissions();
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [equipoDeleting, setEquipoDeleting] = useState<Equipo | null>(null);

    const openDeleteDialog = (equipo: Equipo) => {
        setEquipoDeleting(equipo);
        setDeleteDialogOpen(true);
    };

    return (
        <>
            <Head title="Equipos" />

            <h1 className="sr-only">Equipos</h1>

            <div className="flex flex-col space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Equipos"
                        description="Gestiona los equipos de metrología del laboratorio"
                    />

                    {can('equipos.crear') ? (
                        <CreateEquipoModal options={options}>
                            <Button data-test="equipos-new-button">
                                <Plus /> Nuevo equipo
                            </Button>
                        </CreateEquipoModal>
                    ) : null}
                </div>

                <LimpiarFiltrosServidor valores={filtros} url={index().url} />

                <div className="overflow-x-auto rounded-lg border">
                    <table
                        ref={redimension.tablaRef}
                        style={redimension.estiloTabla}
                        className={cn(
                            'w-full text-sm',
                            redimension.anchos &&
                                '[&_td]:break-words [&_th]:break-words',
                        )}
                    >
                        <ColgroupAnchos
                            claves={CLAVES_COLUMNAS}
                            anchos={redimension.anchos}
                        />
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <ThRedimensionable
                                    clave="codigo"
                                    redimension={redimension}
                                >
                                    Código
                                </ThRedimensionable>
                                <ThRedimensionable
                                    clave="modelo"
                                    redimension={redimension}
                                >
                                    Modelo
                                </ThRedimensionable>
                                <ThRedimensionable
                                    clave="tipo_equipo"
                                    redimension={redimension}
                                >
                                    Tipo de equipo
                                </ThRedimensionable>
                                <ThRedimensionable
                                    clave="fabricante"
                                    redimension={redimension}
                                >
                                    Fabricante
                                </ThRedimensionable>
                                <ThRedimensionable
                                    clave="cliente"
                                    redimension={redimension}
                                >
                                    Cliente
                                </ThRedimensionable>
                                <ThRedimensionable
                                    clave="vencimiento"
                                    redimension={redimension}
                                >
                                    Vencimiento
                                </ThRedimensionable>
                                <ThRedimensionable
                                    clave="activo"
                                    redimension={redimension}
                                >
                                    Estado
                                </ThRedimensionable>
                                <th className="px-4 py-3" />
                            </tr>
                            <FilaFiltrosServidor
                                columnas={COLUMNAS_FILTRO}
                                valores={filtros}
                                url={index().url}
                            />
                        </thead>
                        <tbody className="divide-y">
                            {equipos.data.map((equipo) => (
                                <tr key={equipo.id} data-test="equipo-row">
                                    <td className="px-4 py-3 font-medium">
                                        {equipo.codigo}
                                    </td>
                                    <td className="px-4 py-3">
                                        {equipo.modelo}
                                    </td>
                                    <td className="px-4 py-3">
                                        {equipo.tipo_equipo?.nombre ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {equipo.fabricante?.nombre ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {equipo.cliente?.nombre ?? '—'}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        data-test="equipo-vencimiento"
                                    >
                                        <VencimientoEquipo equipo={equipo} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                equipo.activo
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {equipo.activo
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            {can('equipos.editar') ? (
                                                <EditEquipoModal
                                                    equipo={equipo}
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
                                                    onClick={() =>
                                                        openDeleteDialog(equipo)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {equipos.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        {Object.keys(filtros).length > 0
                                            ? 'No se encontraron equipos con esos filtros.'
                                            : 'Aún no has registrado equipos.'}
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>

                {equipos.last_page > 1 ? (
                    <div className="flex flex-wrap items-center gap-1">
                        {equipos.links.map((link, index) =>
                            link.url ? (
                                <Button
                                    key={index}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    asChild
                                >
                                    <Link
                                        href={link.url}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                </Button>
                            ) : (
                                <Button
                                    key={index}
                                    variant="outline"
                                    size="sm"
                                    disabled
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </div>
                ) : null}
            </div>

            <DeleteEquipoModal
                equipo={equipoDeleting}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />
        </>
    );
}

EquiposIndex.layout = {
    breadcrumbs: [
        {
            title: 'Equipos',
            href: index(),
        },
    ],
};
