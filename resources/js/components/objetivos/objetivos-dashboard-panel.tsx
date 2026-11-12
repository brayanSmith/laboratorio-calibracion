import { Activity, ClipboardCheck, Clock, Target } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import BotonAccion from '@/components/boton-accion';
import ObjetivosMensualesModal from '@/components/dashboard/objetivos-mensuales-modal';
import ObjetivosModal from '@/components/dashboard/objetivos-modal';
import type { Opcion } from '@/components/objetivos/tipos';
import type { ModuloObjetivo } from '@/types';

/** Tarjeta de un indicador del dashboard con el botón para definir sus objetivos. */
function TarjetaIndicador({
    titulo,
    descripcion,
    icono: Icono,
    children,
}: {
    titulo: string;
    descripcion: string;
    icono: LucideIcon;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-4 rounded-xl border p-4">
            <div className="flex flex-wrap items-center gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icono className="h-5 w-5" />
                </div>
                <div className="flex-1">
                    <h2 className="font-semibold">{titulo}</h2>
                    <p className="text-sm text-muted-foreground">
                        {descripcion}
                    </p>
                </div>
            </div>
            {children}
        </div>
    );
}

const ANUALES: {
    modulo: ModuloObjetivo;
    titulo: string;
    descripcion: string;
    icono: LucideIcon;
}[] = [
    {
        modulo: 'TIEMPOS_ATENCION',
        titulo: 'Tiempos de atención de equipos',
        descripcion: 'Objetivos anuales del tiempo entre ingreso y despacho.',
        icono: Clock,
    },
    {
        modulo: 'CUMPLIMIENTO',
        titulo: 'Cumplimiento del plan',
        descripcion:
            'Objetivos anuales de equipos programados frente a atendidos.',
        icono: ClipboardCheck,
    },
];

/**
 * Objetivos de los indicadores del dashboard: los anuales por indicador y los de
 * desempeño por técnico, que se definen por tipo de equipo.
 */
export default function ObjetivosDashboardPanel({
    tiposEquipo,
}: {
    tiposEquipo: Opcion[];
}) {
    const [tipoEquipo, setTipoEquipo] = useState<Opcion | null>(null);

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            {ANUALES.map((indicador) => (
                <TarjetaIndicador
                    key={indicador.modulo}
                    titulo={indicador.titulo}
                    descripcion={indicador.descripcion}
                    icono={indicador.icono}
                >
                    <div>
                        <ObjetivosModal
                            modulo={indicador.modulo}
                            titulo={indicador.titulo}
                            onCambio={() => {}}
                        />
                    </div>
                </TarjetaIndicador>
            ))}

            <div className="lg:col-span-2">
                <TarjetaIndicador
                    titulo="Desempeño por técnico"
                    descripcion="Cantidad y tiempo de mantenimientos y calibraciones al mes, por tipo de equipo."
                    icono={Activity}
                >
                    {tiposEquipo.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                            Aún no hay tipos de equipo registrados.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {tiposEquipo.map((tipo) => (
                                <li
                                    key={tipo.id}
                                    className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                    data-test="objetivos-tipo-equipo-row"
                                >
                                    <span className="font-medium">
                                        {tipo.nombre}
                                    </span>
                                    <BotonAccion
                                        icono={Target}
                                        etiqueta="Objetivos de desempeño"
                                        onClick={() => setTipoEquipo(tipo)}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </TarjetaIndicador>
            </div>

            {tipoEquipo ? (
                <ObjetivosMensualesModal
                    modulo="DESEMPENO_TECNICOS"
                    tipoEquipo={tipoEquipo}
                    open
                    onOpenChange={(abierto) => {
                        if (!abierto) {
                            setTipoEquipo(null);
                        }
                    }}
                />
            ) : null}
        </div>
    );
}
