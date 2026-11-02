import { Activity, ClipboardCheck, Clock } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import IndicadorCumplimiento from '@/components/dashboard/indicador-cumplimiento';
import IndicadorDesempenoTecnicos from '@/components/dashboard/indicador-desempeno-tecnicos';
import IndicadorTiemposAtencion from '@/components/dashboard/indicador-tiempos-atencion';
import type { IndicadoresDashboard } from '@/types';

export type PestanaDashboard = {
    value: string;
    titulo: string;
    icono: LucideIcon;
    contenido: ReactNode;
};

/** Una sección de indicadores: encabezado con su icono y debajo sus tarjetas y tablas. */
function SeccionIndicador({
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
        <section className="grid gap-4">
            <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icono className="h-5 w-5" />
                </div>
                <div>
                    <h2 className="text-lg font-semibold">{titulo}</h2>
                    <p className="text-sm text-muted-foreground">
                        {descripcion}
                    </p>
                </div>
            </div>
            {children}
        </section>
    );
}

/**
 * Pestañas de indicadores del Dashboard; cada una solo aparece si el rol tiene su
 * permiso.
 */
export function pestanasIndicadores(
    indicadores: IndicadoresDashboard,
): PestanaDashboard[] {
    return [
        {
            value: 'tiempos-atencion',
            titulo: 'Tiempos de atención',
            icono: Clock,
            visible: indicadores.permisos.tiempos_atencion,
            contenido: (
                <SeccionIndicador
                    titulo="Tiempos de atención de equipos"
                    icono={Clock}
                    descripcion="Tiempo total entre el ingreso y el despacho de cada equipo."
                >
                    <IndicadorTiemposAtencion indicadores={indicadores} />
                </SeccionIndicador>
            ),
        },
        {
            value: 'desempeno-tecnicos',
            titulo: 'Desempeño por técnico',
            icono: Activity,
            visible: indicadores.permisos.desempeno_tecnicos,
            contenido: (
                <SeccionIndicador
                    titulo="Desempeño por técnico"
                    icono={Activity}
                    descripcion="Mantenimientos y calibraciones finalizados y su tiempo promedio."
                >
                    <IndicadorDesempenoTecnicos indicadores={indicadores} />
                </SeccionIndicador>
            ),
        },
        {
            value: 'cumplimiento',
            titulo: 'Cumplimiento',
            icono: ClipboardCheck,
            visible: indicadores.permisos.cumplimiento,
            contenido: (
                <SeccionIndicador
                    titulo="Cumplimiento"
                    icono={ClipboardCheck}
                    descripcion="Equipos programados frente a los equipos atendidos."
                >
                    <IndicadorCumplimiento indicadores={indicadores} />
                </SeccionIndicador>
            ),
        },
    ]
        .filter((pestana) => pestana.visible)
        .map(({ value, titulo, icono, contenido }) => ({
            value,
            titulo,
            icono,
            contenido,
        }));
}
