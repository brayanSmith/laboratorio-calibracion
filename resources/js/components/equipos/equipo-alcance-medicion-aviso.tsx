import { CircleAlert, CircleCheck } from 'lucide-react';
import { estadoAlcanceMedicion } from '@/lib/alcance-medicion-equipo';
import type { EquipoAlcanceMedicionClave } from '@/types';

type Props = {
    alcances: EquipoAlcanceMedicionClave[];
    tipoEquipoId: string | undefined;
    modelo: string;
    alcanceIndicacion: string;
};

/**
 * Avisa, mientras se llena el formulario del equipo, si su tipo de equipo, modelo y
 * alcance de indicación ya tienen alcance de medición, para que quede pendiente de
 * registrar si no. No muestra nada mientras falte alguno de los tres.
 */
export default function EquipoAlcanceMedicionAviso({
    alcances,
    tipoEquipoId,
    modelo,
    alcanceIndicacion,
}: Props) {
    const estado = estadoAlcanceMedicion(
        alcances,
        tipoEquipoId,
        modelo,
        alcanceIndicacion,
    );

    if (estado === 'SIN_DATOS') {
        return null;
    }

    if (estado === 'REGISTRADO') {
        return (
            <p
                className="flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
                data-test="equipo-alcance-medicion-aviso"
            >
                <CircleCheck className="h-4 w-4 shrink-0" />
                Ya hay alcance de medición registrado para este tipo de equipo,
                modelo y alcance de indicación.
            </p>
        );
    }

    return (
        <p
            className="flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            data-test="equipo-alcance-medicion-aviso"
        >
            <CircleAlert className="h-4 w-4 shrink-0" />
            Aún no hay alcance de medición para este tipo de equipo, modelo y
            alcance de indicación: queda pendiente de registrarlo en Alcances de
            medición.
        </p>
    );
}
