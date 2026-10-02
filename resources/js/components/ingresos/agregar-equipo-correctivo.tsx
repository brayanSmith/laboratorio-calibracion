import { useHttp } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import Combobox from '@/components/combobox';
import { estadosProgramacion } from '@/components/equipos/equipo-programacion-fields';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { equiposDisponibles as equiposDisponiblesRoute } from '@/routes/equipo-programaciones';
import type { EquipoDisponibleItem } from '@/types';

export type CorrectivoPendiente = {
    tempId: string;
    equipoId: string;
    equipoLabel: string;
    fallaDetectada: string;
};

/** Agendado en verde. Coincide con el resto de badges de estado_programacion. */
const colorBadgeAgendado = '!border-transparent !bg-emerald-500 !text-white';

type AgregarEquipoCorrectivoProps = {
    bahiaId: string;
    onAgregar: (pendiente: Omit<CorrectivoPendiente, 'tempId'>) => void;
    onCancelar: () => void;
};

/**
 * Formulario para anotar, localmente, un equipo que falló "de la nada" (no tenía
 * servicio programado) en la bahía del ingreso. No llama al servidor: solo junta el
 * equipo y la falla detectada en la lista de pendientes, que se guarda junto con el
 * resto del formulario (del ingreso, o de "Recibir equipos") al enviarlo.
 */
export function AgregarEquipoCorrectivo({
    bahiaId,
    onAgregar,
    onCancelar,
}: AgregarEquipoCorrectivoProps) {
    const [equipoId, setEquipoId] = useState('');
    const [fallaDetectada, setFallaDetectada] = useState('');
    const { get: cargarEquipos, response: equipos } = useHttp<
        { bahia_id: string },
        EquipoDisponibleItem[]
    >();

    useEffect(() => {
        void cargarEquipos(
            equiposDisponiblesRoute({ query: { bahia_id: bahiaId } }).url,
        );
        // Solo se vuelve a cargar si cambia la bahía del ingreso.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [bahiaId]);

    const equipoSeleccionado = (equipos ?? []).find(
        (equipo) => equipo.id.toString() === equipoId,
    );

    const handleAgregar = () => {
        if (!equipoSeleccionado || !fallaDetectada) {
            return;
        }

        onAgregar({
            equipoId,
            equipoLabel: `${equipoSeleccionado.codigo} · ${equipoSeleccionado.modelo}`,
            fallaDetectada,
        });
        setEquipoId('');
        setFallaDetectada('');
    };

    return (
        <div
            className="space-y-3 rounded-md border bg-muted/30 p-3"
            data-test="agregar-equipo-correctivo-form"
        >
            <div className="grid gap-2">
                <Label htmlFor="equipo-correctivo">Equipo</Label>
                <Combobox
                    id="equipo-correctivo"
                    value={equipoId}
                    onValueChange={setEquipoId}
                    options={(equipos ?? []).map((equipo) => ({
                        id: equipo.id,
                        label: `${equipo.codigo} · ${equipo.modelo}`,
                    }))}
                    placeholder="Selecciona un equipo"
                    searchPlaceholder="Buscar equipo..."
                    emptyMessage="Sin equipos en esta bahía."
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="falla-detectada">Falla detectada</Label>
                <Textarea
                    id="falla-detectada"
                    value={fallaDetectada}
                    onChange={(event) => setFallaDetectada(event.target.value)}
                    placeholder="Describe la falla detectada en el equipo"
                    rows={2}
                />
            </div>

            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onCancelar}
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    size="sm"
                    onClick={handleAgregar}
                    disabled={!equipoId || !fallaDetectada}
                    data-test="agregar-equipo-correctivo-guardar"
                >
                    Agregar
                </Button>
            </div>
        </div>
    );
}

type CorrectivoPendienteItemProps = {
    pendiente: CorrectivoPendiente;
    index: number;
    onQuitar: () => void;
    errors: Partial<Record<string, string>>;
    /** Prefijo del campo bajo el que viaja en el formulario, ej: "programaciones_correctivas". */
    campo: string;
};

/**
 * Una fila "por guardar": un equipo correctivo que el usuario anotó en esta sesión,
 * pero que todavía no existe en el servidor. Escribe sus propios inputs ocultos
 * (campo[index][...]) para que viajen junto con el resto del formulario.
 */
export function CorrectivoPendienteItem({
    pendiente,
    index,
    onQuitar,
    errors,
    campo,
}: CorrectivoPendienteItemProps) {
    const prefijo = `${campo}.${index}`;

    return (
        <li
            className="flex items-start justify-between gap-3 px-3 py-2"
            data-test="correctivo-pendiente-item"
        >
            <div className="space-y-1">
                <span className="text-sm font-medium">
                    {pendiente.equipoLabel}
                </span>
                <p className="text-xs text-muted-foreground">
                    {pendiente.fallaDetectada}
                </p>
                <InputError
                    message={
                        errors[`${prefijo}.equipo_id`] ??
                        errors[`${prefijo}.falla_detectada`]
                    }
                />
            </div>

            <div className="flex shrink-0 items-center gap-2">
                <Badge className={colorBadgeAgendado}>
                    {
                        estadosProgramacion.find(
                            (option) => option.value === 'AGENDADO',
                        )?.label
                    }
                </Badge>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-6 w-6"
                    onClick={onQuitar}
                    aria-label="Quitar equipo"
                    data-test="correctivo-pendiente-quitar"
                >
                    <Trash2 className="h-3.5 w-3.5" />
                </Button>
            </div>

            <input
                type="hidden"
                name={`${campo}[${index}][equipo_id]`}
                value={pendiente.equipoId}
            />
            <input
                type="hidden"
                name={`${campo}[${index}][falla_detectada]`}
                value={pendiente.fallaDetectada}
            />
        </li>
    );
}
