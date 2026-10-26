import { useMemo, useState } from 'react';
import EquipoProgramacionesBuscador from '@/components/ingresos/equipo-programaciones-buscador';
import InputError from '@/components/input-error';
import MultiCombobox from '@/components/multi-combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { equiposPorBahia } from '@/lib/equipos-por-bahia';
import type { Ingreso, IngresoOptions } from '@/types';

type Props = {
    ingreso?: Ingreso;
    options: IngresoOptions;
    errors: Partial<Record<string, string>>;
    /** Vista previa de los equipos que se esperan recibir, para crear o revisar el ingreso. */
    mostrarBuscador?: boolean;
};

/** Primer error de los campos que empiezan con el prefijo (ej. una bahía inválida). */
function primerError(
    errors: Partial<Record<string, string>>,
    prefijo: string,
): string | undefined {
    return Object.entries(errors).find(([campo]) =>
        campo.startsWith(prefijo),
    )?.[1];
}

/**
 * Bahías, fechas y equipos del ingreso. El técnico que recibe, el cliente que entrega,
 * la firma, la novedad y el motivo de cancelación se manejan aparte, desde "Recibir"
 * (ver recibir-ingreso-modal.tsx), no desde este formulario.
 */
export default function IngresoFormFields({
    ingreso,
    options,
    errors,
    mostrarBuscador = false,
}: Props) {
    const [desde, setDesde] = useState(ingreso?.desde ?? '');
    const [hasta, setHasta] = useState(ingreso?.hasta ?? '');
    const [bahiaIds, setBahiaIds] = useState<string[]>(
        () => ingreso?.bahia_ids.map(String) ?? [],
    );
    const conteoPorBahia = useMemo(
        () => equiposPorBahia(options.programacionesPendientes, desde, hasta),
        [options.programacionesPendientes, desde, hasta],
    );
    const conRango = desde !== '' && hasta !== '';

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="desde">Desde</Label>
                <Input
                    id="desde"
                    name="desde"
                    type="date"
                    value={desde}
                    onChange={(event) => {
                        const nuevoDesde = event.target.value;

                        setDesde(nuevoDesde);

                        // Si "hasta" queda antes de la nueva fecha "desde", se ajusta para no
                        // dejar un rango inválido, aunque el usuario ya la hubiera escrito.
                        if (hasta !== '' && hasta < nuevoDesde) {
                            setHasta(nuevoDesde);
                        }
                    }}
                    required
                />
                <InputError message={errors.desde} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="hasta">Hasta</Label>
                <Input
                    id="hasta"
                    name="hasta"
                    type="date"
                    value={hasta}
                    onChange={(event) => setHasta(event.target.value)}
                    min={desde || undefined}
                    required
                />
                <InputError message={errors.hasta} />
            </div>

            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="bahia_ids">Bahías</Label>
                <MultiCombobox
                    id="bahia_ids"
                    name="bahia_ids"
                    value={bahiaIds}
                    onValueChange={setBahiaIds}
                    options={options.bahias.map((bahia) => ({
                        id: bahia.id,
                        label: bahia.nombre,
                        contador: conteoPorBahia.get(bahia.id) ?? 0,
                    }))}
                    tituloContador={
                        conRango
                            ? 'Equipos con servicio programado entre Desde y Hasta'
                            : 'Equipos con servicio programado pendiente (elige Desde y Hasta para filtrar)'
                    }
                    placeholder="Selecciona una o varias bahías"
                    searchPlaceholder="Buscar bahía..."
                    emptyMessage="Ninguna bahía coincide."
                    plural="bahías"
                />
                <p className="text-xs text-muted-foreground">
                    El número de cada bahía es cuántos equipos tienen servicio
                    programado{' '}
                    {conRango
                        ? 'entre Desde y Hasta.'
                        : 'pendiente; elige Desde y Hasta para ver los del rango.'}
                </p>
                <InputError
                    message={
                        errors.bahia_ids ?? primerError(errors, 'bahia_ids.')
                    }
                />
            </div>

            {mostrarBuscador ? (
                <div className="grid gap-2 sm:col-span-2">
                    <EquipoProgramacionesBuscador
                        desde={desde}
                        hasta={hasta}
                        bahiaIds={bahiaIds}
                        resultadosIniciales={ingreso?.equipo_programaciones}
                        errors={errors}
                    />
                </div>
            ) : null}
        </div>
    );
}
