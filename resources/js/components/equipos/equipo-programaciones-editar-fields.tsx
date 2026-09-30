import {
    estadosVencimiento,
    tiposServicio,
} from '@/components/equipos/equipo-programacion-fields';
import EquipoProgramacionesFields from '@/components/equipos/equipo-programaciones-fields';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { EquipoProgramacion } from '@/types';

type Props = {
    programaciones: EquipoProgramacion[];
    errors: Partial<Record<string, string>>;
};

/** El valor guardado es un CSV de uno o varios tipos, ej: "MANTENIMIENTO,CALIBRACION". */
function tiposServicioLabel(value: string): string {
    return value
        .split(',')
        .map(
            (tipo) =>
                tiposServicio.find((option) => option.value === tipo)?.label ??
                tipo,
        )
        .join(' + ');
}

function estadoVencimientoLabel(value: string): string {
    return (
        estadosVencimiento.find((option) => option.value === value)?.label ??
        value
    );
}

/**
 * Programaciones existentes (con casilla para marcarlas a eliminar) más el
 * repetidor para agregar nuevas. Todo viaja en el mismo envío del formulario,
 * sin botones de guardar por separado.
 */
export default function EquipoProgramacionesEditarFields({
    programaciones,
    errors,
}: Props) {
    return (
        <div className="space-y-3">
            {programaciones.length > 0 ? (
                <ul className="divide-y rounded-lg border">
                    {programaciones.map((programacion) => (
                        <li
                            key={programacion.id}
                            className="flex items-center justify-between gap-2 px-3 py-2"
                            data-test="equipo-programacion-item"
                        >
                            <div className="space-y-1">
                                <div className="flex items-center gap-2 text-sm">
                                    <span className="font-medium">
                                        {tiposServicioLabel(
                                            programacion.tipo_servicio,
                                        )}
                                    </span>
                                    <Badge variant="secondary">
                                        {estadoVencimientoLabel(
                                            programacion.estado_vencimiento,
                                        )}
                                    </Badge>
                                </div>
                                {programacion.fecha_proximo_servicio ? (
                                    <p className="text-xs text-muted-foreground">
                                        Próximo servicio:{' '}
                                        {programacion.fecha_proximo_servicio.slice(
                                            0,
                                            10,
                                        )}
                                    </p>
                                ) : null}
                            </div>

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
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-center text-sm text-muted-foreground">
                    Este equipo aún no tiene programaciones de servicio.
                </p>
            )}

            <EquipoProgramacionesFields errors={errors} />
        </div>
    );
}
