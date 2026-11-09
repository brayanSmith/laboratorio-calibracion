import { Activity, ClipboardCheck, Clock } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { RecargaIndicadorContext } from '@/components/dashboard/indicador-comun';
import IndicadorCumplimiento from '@/components/dashboard/indicador-cumplimiento';
import IndicadorDesempenoTecnicos from '@/components/dashboard/indicador-desempeno-tecnicos';
import IndicadorTiemposAtencion from '@/components/dashboard/indicador-tiempos-atencion';
import ObjetivosModal from '@/components/dashboard/objetivos-modal';
import { usePermissions } from '@/hooks/use-permissions';
import type { IndicadoresDashboard, ModuloObjetivo } from '@/types';

export type PestanaDashboard = {
    value: string;
    titulo: string;
    icono: LucideIcon;
    contenido: ReactNode;
};

/**
 * Una sección de indicadores: encabezado con su icono (y el botón de sus objetivos
 * anuales, para quien puede definirlos) y debajo sus tarjetas, gráficos y tablas.
 */
function SeccionIndicador({
    titulo,
    descripcion,
    icono: Icono,
    modulo,
    children,
}: {
    titulo: string;
    descripcion: string;
    icono: LucideIcon;
    modulo: ModuloObjetivo;
    children: ReactNode;
}) {
    const { can } = usePermissions();
    // Sube al guardar objetivos: el indicador se vuelve a pedir con la línea nueva.
    const [recarga, setRecarga] = useState(0);

    return (
        <section className="grid gap-4">
            <div className="flex flex-wrap items-center gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icono className="h-5 w-5" />
                </div>
                <div className="flex-1">
                    <h2 className="text-lg font-semibold">{titulo}</h2>
                    <p className="text-sm text-muted-foreground">
                        {descripcion}
                    </p>
                </div>
                {/* Los de desempeño (por mes) se definen en cada tipo de equipo. */}
                {can('dashboard.objetivos') &&
                modulo !== 'DESEMPENO_TECNICOS' ? (
                    <ObjetivosModal
                        modulo={modulo}
                        titulo={titulo}
                        onCambio={() => setRecarga((actual) => actual + 1)}
                    />
                ) : null}
            </div>
            <RecargaIndicadorContext.Provider value={recarga}>
                {children}
            </RecargaIndicadorContext.Provider>
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
                    modulo="TIEMPOS_ATENCION"
                    descripcion="Tiempo total entre el ingreso y el despacho de cada equipo."
                >
                    <IndicadorTiemposAtencion />
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
                    modulo="DESEMPENO_TECNICOS"
                    descripcion="Mantenimientos y calibraciones finalizados y su tiempo promedio."
                >
                    <IndicadorDesempenoTecnicos />
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
                    modulo="CUMPLIMIENTO"
                    descripcion="Equipos programados frente a los equipos atendidos."
                >
                    <IndicadorCumplimiento />
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
