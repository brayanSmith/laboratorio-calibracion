import { FileText } from 'lucide-react';
import { type PropsWithChildren, type ReactNode, useState } from 'react';
import {
    estadoVencimientoLabel,
    estaCompletada,
    TARJETA_VENCIMIENTO,
    tiposServicioLabel,
} from '@/components/equipos/equipo-programaciones-editar-fields';
import EquipoSeguimientoModal from '@/components/equipos/equipo-seguimiento-modal';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import {
    faseLabel,
    formatearFechaSeguimiento,
    seguimientoLabel,
} from '@/lib/seguimiento-programacion';
import {
    BADGE_VENCIMIENTO,
    estaGestionada,
} from '@/lib/vencimiento-programacion';
import type { Equipo, EquipoFormOptions, EquipoProgramacion } from '@/types';

type Props = PropsWithChildren<{
    equipo: Equipo;
    options: EquipoFormOptions;
}>;

function Dato({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="grid gap-0.5">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="text-sm font-medium break-words">
                {value === null || value === undefined || value === ''
                    ? '—'
                    : value}
            </span>
        </div>
    );
}

function Seccion({
    titulo,
    descripcion,
    children,
}: PropsWithChildren<{ titulo: string; descripcion: string }>) {
    return (
        <div className="space-y-4">
            <Heading variant="small" title={titulo} description={descripcion} />
            {children}
        </div>
    );
}

const siNo = (valor: boolean) => (valor ? 'Sí' : 'No');

/** Separa con un espacio el símbolo de la unidad que se guarda pegado al valor
 * ("0.005pulg" se muestra como "0.005 pulg"). */
function conEspacioAntesDelSimbolo(
    valor: string | null | undefined,
    simbolo?: string,
): string | null | undefined {
    if (!valor || !simbolo || !valor.endsWith(simbolo)) {
        return valor;
    }

    return `${valor.slice(0, -simbolo.length).trimEnd()} ${simbolo}`;
}

/** Vista de detalle (solo lectura) de un equipo, para quien no puede editarlo: sus datos,
 * documentos y programaciones, con el seguimiento de cada una. */
export default function ViewEquipoModal({ equipo, options, children }: Props) {
    const [seguimientoDe, setSeguimientoDe] =
        useState<EquipoProgramacion | null>(null);

    const especificacion = equipo.equipo_especificacion_tecnica;
    const ficha = equipo.ficha_tecnica;
    const documentos = equipo.equipo_documentos ?? [];
    const programaciones = equipo.equipo_programaciones ?? [];
    const canVerProgramaciones = usePermissions().can('equipos.programaciones');
    const unidad = options.unidadesMedida.find(
        (opcion) => opcion.id === especificacion?.unidad_medida_id,
    );

    return (
        <>
            <Dialog>
                <DialogTrigger asChild>{children}</DialogTrigger>
                <DialogContent
                    className="max-h-[85vh] overflow-y-auto sm:max-w-2xl"
                    data-test="equipo-view-modal"
                >
                    <DialogHeader>
                        <DialogTitle>Equipo {equipo.codigo}</DialogTitle>
                        <DialogDescription>
                            {equipo.modelo} · Detalle del equipo
                        </DialogDescription>
                    </DialogHeader>

                    <Tabs defaultValue="general">
                        <TabsList
                            className={cn(
                                'grid w-full',
                                canVerProgramaciones
                                    ? 'grid-cols-2'
                                    : 'grid-cols-1',
                            )}
                        >
                            <TabsTrigger value="general">General</TabsTrigger>
                            {canVerProgramaciones ? (
                                <TabsTrigger value="programaciones">
                                    Programaciones ({programaciones.length})
                                </TabsTrigger>
                            ) : null}
                        </TabsList>

                        <TabsContent value="general" className="space-y-6 pt-2">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Dato label="Código" value={equipo.codigo} />
                                <Dato label="Modelo" value={equipo.modelo} />
                                <Dato
                                    label="Número de serie"
                                    value={equipo.numero_serie}
                                />
                                <Dato
                                    label="Tipo de tecnología"
                                    value={
                                        equipo.tipo_tecnologia === 'DIGITAL'
                                            ? 'Digital'
                                            : 'Analógico'
                                    }
                                />
                                <Dato
                                    label="Tipo de equipo"
                                    value={equipo.tipo_equipo?.nombre}
                                />
                                <Dato
                                    label="Fabricante"
                                    value={equipo.fabricante?.nombre}
                                />
                                <Dato
                                    label="Cliente"
                                    value={equipo.cliente?.nombre}
                                />
                                <Dato
                                    label="Condición actual"
                                    value={equipo.condicion_actual}
                                />
                                <Dato
                                    label="Estado"
                                    value={
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
                                    }
                                />
                                <Dato
                                    label="Patrón de referencia"
                                    value={siNo(equipo.patron_referencia)}
                                />
                                <Dato
                                    label="Requiere programación"
                                    value={siNo(equipo.requiere_programacion)}
                                />
                                <div className="sm:col-span-2">
                                    <Dato label="Notas" value={equipo.notas} />
                                </div>
                            </div>

                            <Separator />

                            <Seccion
                                titulo="Ubicación"
                                descripcion="Dónde se encuentra físicamente el equipo"
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Dato
                                        label="Área"
                                        value={equipo.area?.nombre}
                                    />
                                    <Dato
                                        label="Bahía"
                                        value={equipo.bahia?.nombre}
                                    />
                                </div>
                            </Seccion>

                            <Separator />

                            <Seccion
                                titulo="Ficha técnica"
                                descripcion="Datos de adquisición del equipo"
                            >
                                {ficha ? (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Dato
                                            label="País de procedencia"
                                            value={ficha.pais_procedencia}
                                        />
                                        <Dato
                                            label="Número de activo"
                                            value={ficha.numero_activo}
                                        />
                                        <Dato
                                            label="Proveedor"
                                            value={ficha.proveedor}
                                        />
                                        <Dato
                                            label="Costo (USD)"
                                            value={ficha.costo_usd}
                                        />
                                        <Dato
                                            label="Fecha de adquisición"
                                            value={ficha.fecha_adquisicion?.slice(
                                                0,
                                                10,
                                            )}
                                        />
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Sin ficha técnica registrada.
                                    </p>
                                )}
                            </Seccion>

                            <Separator />

                            <Seccion
                                titulo="Especificación técnica"
                                descripcion="Magnitud, unidad y alcance que mide este equipo"
                            >
                                {especificacion ? (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Dato
                                            label="Magnitud"
                                            value={
                                                options.tiposMagnitud.find(
                                                    (opcion) =>
                                                        opcion.id ===
                                                        especificacion.tipo_magnitud_id,
                                                )?.nombre
                                            }
                                        />
                                        <Dato
                                            label="Unidad de medida"
                                            value={
                                                unidad
                                                    ? `${unidad.nombre} (${unidad.simbolo})`
                                                    : null
                                            }
                                        />
                                        <Dato
                                            label="Alcance / indicación"
                                            value={conEspacioAntesDelSimbolo(
                                                especificacion.alcance_indicacion,
                                                unidad?.simbolo,
                                            )}
                                        />
                                        <Dato
                                            label="Precisión"
                                            value={especificacion.precision}
                                        />
                                        <Dato
                                            label="Resolución"
                                            value={conEspacioAntesDelSimbolo(
                                                especificacion.resolucion,
                                                unidad?.simbolo,
                                            )}
                                        />
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Sin especificación técnica registrada.
                                    </p>
                                )}
                            </Seccion>

                            <Separator />

                            <Seccion
                                titulo="Documentos"
                                descripcion="Documentos del equipo"
                            >
                                {documentos.length > 0 ? (
                                    <ul className="divide-y rounded-lg border">
                                        {documentos.map((documento) => (
                                            <li
                                                key={documento.id}
                                                className="px-3 py-2"
                                            >
                                                <a
                                                    href={documento.archivo_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex items-center gap-2 text-sm hover:underline"
                                                >
                                                    <FileText className="h-4 w-4 shrink-0" />
                                                    {documento.nombre}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Este equipo aún no tiene documentos.
                                    </p>
                                )}
                            </Seccion>
                        </TabsContent>

                        <TabsContent value="programaciones" className="pt-2">
                            {programaciones.length > 0 ? (
                                <ul className="space-y-2">
                                    {programaciones.map((programacion) => {
                                        const gestionada =
                                            estaGestionada(programacion);
                                        const ultimo =
                                            programacion.seguimientos?.[0];

                                        return (
                                            <li
                                                key={programacion.id}
                                                className={cn(
                                                    'space-y-3 rounded-lg border p-3',
                                                    !gestionada &&
                                                        TARJETA_VENCIMIENTO[
                                                            programacion
                                                                .estado_vencimiento
                                                        ],
                                                )}
                                                data-test="equipo-programacion-item"
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="space-y-1">
                                                        <p className="text-sm font-medium">
                                                            {tiposServicioLabel(
                                                                programacion.tipo_servicio,
                                                            )}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {programacion.tipo_mantenimiento ===
                                                            'CORRECTIVO'
                                                                ? 'Correctivo'
                                                                : 'Preventivo'}
                                                        </p>
                                                    </div>

                                                    {gestionada ? (
                                                        <Badge
                                                            className={
                                                                estaCompletada(
                                                                    programacion,
                                                                )
                                                                    ? '!border-transparent !bg-emerald-500 !text-white'
                                                                    : '!border-transparent !bg-blue-500 !text-white'
                                                            }
                                                        >
                                                            {estaCompletada(
                                                                programacion,
                                                            )
                                                                ? 'Completado'
                                                                : 'En gestión'}
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            className={
                                                                BADGE_VENCIMIENTO[
                                                                    programacion
                                                                        .estado_vencimiento
                                                                ]
                                                            }
                                                        >
                                                            {estadoVencimientoLabel(
                                                                programacion.estado_vencimiento,
                                                            )}
                                                        </Badge>
                                                    )}
                                                </div>

                                                <div className="space-y-1 text-xs">
                                                    {programacion.falla_detectada ? (
                                                        <p>
                                                            <span className="text-muted-foreground">
                                                                Falla detectada:
                                                            </span>{' '}
                                                            {
                                                                programacion.falla_detectada
                                                            }
                                                        </p>
                                                    ) : null}
                                                    {programacion.fecha_ultimo_servicio ? (
                                                        <p>
                                                            <span className="text-muted-foreground">
                                                                Último servicio:
                                                            </span>{' '}
                                                            {programacion.fecha_ultimo_servicio.slice(
                                                                0,
                                                                10,
                                                            )}
                                                        </p>
                                                    ) : null}
                                                    {!gestionada &&
                                                    programacion.fecha_proximo_servicio ? (
                                                        <p>
                                                            <span className="text-muted-foreground">
                                                                Próximo
                                                                servicio:
                                                            </span>{' '}
                                                            {programacion.fecha_proximo_servicio.slice(
                                                                0,
                                                                10,
                                                            )}{' '}
                                                            <span className="text-muted-foreground">
                                                                (aviso{' '}
                                                                {Math.round(
                                                                    Number(
                                                                        programacion.dias_plazo_vencimiento,
                                                                    ),
                                                                )}{' '}
                                                                días antes)
                                                            </span>
                                                        </p>
                                                    ) : null}
                                                    {ultimo ? (
                                                        <p>
                                                            <span className="text-muted-foreground">
                                                                Último
                                                                seguimiento:
                                                            </span>{' '}
                                                            {faseLabel(
                                                                ultimo.fase,
                                                            )}{' '}
                                                            ·{' '}
                                                            {seguimientoLabel(
                                                                ultimo.seguimiento,
                                                            )}{' '}
                                                            <span className="text-muted-foreground">
                                                                (
                                                                {formatearFechaSeguimiento(
                                                                    ultimo.created_at,
                                                                )}
                                                                )
                                                            </span>
                                                        </p>
                                                    ) : null}
                                                </div>

                                                <div className="border-t pt-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setSeguimientoDe(
                                                                programacion,
                                                            )
                                                        }
                                                        data-test="ver-seguimiento-button"
                                                    >
                                                        Ver seguimiento
                                                    </Button>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            ) : (
                                <p className="text-center text-sm text-muted-foreground">
                                    Este equipo aún no tiene programaciones de
                                    servicio.
                                </p>
                            )}
                        </TabsContent>
                    </Tabs>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="secondary">Cerrar</Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <EquipoSeguimientoModal
                titulo={
                    seguimientoDe
                        ? tiposServicioLabel(seguimientoDe.tipo_servicio)
                        : ''
                }
                seguimientos={seguimientoDe?.seguimientos ?? []}
                open={seguimientoDe !== null}
                onOpenChange={(abierto) => {
                    if (!abierto) {
                        setSeguimientoDe(null);
                    }
                }}
            />
        </>
    );
}
