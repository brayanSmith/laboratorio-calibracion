import { useState } from 'react';
import {
    estadosVencimiento,
    tiposServicio,
} from '@/components/equipos/equipo-programacion-fields';
import EquipoProgramacionesFields from '@/components/equipos/equipo-programaciones-fields';
import EquipoSeguimientoModal from '@/components/equipos/equipo-seguimiento-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import {
    BADGE_VENCIMIENTO,
    estaGestionada,
} from '@/lib/vencimiento-programacion';
import {
    faseLabel,
    formatearFechaSeguimiento,
    seguimientoLabel,
} from '@/lib/seguimiento-programacion';
import type { EquipoProgramacion, EstadoVencimiento } from '@/types';

type Props = {
    programaciones: EquipoProgramacion[];
    errors: Partial<Record<string, string>>;
    /** Tipos de servicio que se marcan por defecto en las programaciones nuevas. */
    tiposSugeridos?: string[];
};

/** El valor guardado es un CSV de uno o varios tipos, ej: "MANTENIMIENTO,CALIBRACION". */
export function tiposServicioLabel(value: string): string {
    return value
        .split(',')
        .map(
            (tipo) =>
                tiposServicio.find((option) => option.value === tipo)?.label ??
                tipo,
        )
        .join(' + ');
}

export function estadoVencimientoLabel(value: string): string {
    return (
        estadosVencimiento.find((option) => option.value === value)?.label ??
        value
    );
}

/** La tarjeta se tiñe solo cuando hay algo que atender. */
export const TARJETA_VENCIMIENTO: Record<EstadoVencimiento, string> = {
    AL_DIA: '',
    PROXIMO_A_VENCER:
        'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30',
    VENCIDO: 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/30',
};

export function estaCompletada(programacion: EquipoProgramacion): boolean {
    const ultimo = programacion.seguimientos?.[0];

    return ultimo?.fase === 'DESPACHO' && ultimo.seguimiento === 'ENTREGADO';
}

/**
 * Programaciones existentes (con casilla para marcarlas a eliminar) más el
 * repetidor para agregar nuevas. Todo viaja en el mismo envío del formulario,
 * sin botones de guardar por separado.
 */
export default function EquipoProgramacionesEditarFields({
    programaciones,
    errors,
    tiposSugeridos,
}: Props) {
    const [seguimientoDe, setSeguimientoDe] =
        useState<EquipoProgramacion | null>(null);

    return (
        <div className="space-y-3">
            {programaciones.length > 0 ? (
                <ul className="space-y-2">
                    {programaciones.map((programacion) => {
                        const gestionada = estaGestionada(programacion);
                        const ultimoSeguimiento =
                            programacion.seguimientos?.[0];

                        return (
                            <li
                                key={programacion.id}
                                className={cn(
                                    'space-y-3 rounded-lg border p-3',
                                    !gestionada &&
                                        TARJETA_VENCIMIENTO[
                                            programacion.estado_vencimiento
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
                                                estaCompletada(programacion)
                                                    ? '!border-transparent !bg-emerald-500 !text-white'
                                                    : '!border-transparent !bg-blue-500 !text-white'
                                            }
                                            data-test="programacion-gestion-badge"
                                        >
                                            {estaCompletada(programacion)
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
                                            data-test="programacion-vencimiento-badge"
                                        >
                                            {estadoVencimientoLabel(
                                                programacion.estado_vencimiento,
                                            )}
                                        </Badge>
                                    )}
                                </div>

                                <div className="space-y-1 text-xs">
                                    {!gestionada &&
                                    programacion.fecha_proximo_servicio ? (
                                        <p>
                                            <span className="text-muted-foreground">
                                                Próximo servicio:
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

                                    {ultimoSeguimiento ? (
                                        <p data-test="ultimo-seguimiento">
                                            <span className="text-muted-foreground">
                                                Último seguimiento:
                                            </span>{' '}
                                            {faseLabel(ultimoSeguimiento.fase)}{' '}
                                            ·{' '}
                                            {seguimientoLabel(
                                                ultimoSeguimiento.seguimiento,
                                            )}{' '}
                                            <span className="text-muted-foreground">
                                                (
                                                {formatearFechaSeguimiento(
                                                    ultimoSeguimiento.created_at,
                                                )}
                                                )
                                            </span>
                                        </p>
                                    ) : null}
                                </div>

                                <div className="flex items-center justify-between gap-3 border-t pt-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setSeguimientoDe(programacion)
                                        }
                                        data-test="ver-seguimiento-button"
                                    >
                                        Ver seguimiento
                                    </Button>

                                    {programacion.eliminable ? (
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id={`programacion-eliminar-${programacion.id}`}
                                                name="programaciones_eliminar[]"
                                                value={programacion.id.toString()}
                                            />
                                            <Label
                                                htmlFor={`programacion-eliminar-${programacion.id}`}
                                                className="text-sm font-normal text-muted-foreground"
                                            >
                                                Eliminar
                                            </Label>
                                        </div>
                                    ) : null}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            ) : (
                <p className="text-center text-sm text-muted-foreground">
                    Este equipo aún no tiene programaciones de servicio.
                </p>
            )}

            <EquipoProgramacionesFields
                errors={errors}
                tiposSugeridos={tiposSugeridos}
            />

            <EquipoSeguimientoModal
                titulo={
                    seguimientoDe
                        ? tiposServicioLabel(seguimientoDe.tipo_servicio)
                        : ''
                }
                seguimientos={seguimientoDe?.seguimientos ?? []}
                open={seguimientoDe !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSeguimientoDe(null);
                    }
                }}
            />
        </div>
    );
}
