import { Head } from '@inertiajs/react';
import { AlertTriangle, Boxes, CheckCircle2, Clock } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import EquiposTabla from '@/components/equipos/equipos-tabla';
import EquiposUrgentes from '@/components/equipos/equipos-urgentes';
import type { EquipoUrgente } from '@/components/equipos/equipos-urgentes';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { Equipo, EquipoFormOptions } from '@/types';

type Props = {
    /** Cada conteo llega null si el rol no tiene habilitada esa tarjeta. */
    total_equipos: number | null;
    vencimientos: {
        al_dia: number | null;
        proximos_a_vencer: number | null;
        vencidos: number | null;
    };
    /** Solo llegan si el rol puede ver equipos y tiene habilitado ese bloque. */
    equipos_urgentes?: EquipoUrgente[];
    equipos?: Equipo[];
    options?: EquipoFormOptions;
};

type TarjetaVencimiento = {
    titulo: string;
    valor: number | null;
    icono: LucideIcon;
    color: string;
    /** Botón en un tono más intenso que el fondo suave de la tarjeta. */
    colorBoton: string;
    testId: string;
    /** Estado de vencimiento por el que la tarjeta filtra la tabla; null muestra todos los equipos. */
    vencimiento: string | null;
};

export default function Dashboard({
    total_equipos,
    vencimientos,
    equipos_urgentes,
    equipos,
    options,
}: Props) {
    // Cada pulsación de "Ver" vuelve a montar la tabla ya filtrada, sin pedir nada al servidor.
    const [vista, setVista] = useState<{
        n: number;
        vencimiento: string | null;
    }>({ n: 0, vencimiento: null });
    const tarjetas: TarjetaVencimiento[] = [
        {
            titulo: 'Todos los equipos',
            valor: total_equipos,
            icono: Boxes,
            color: 'border-sky-200 bg-sky-100 text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200',
            colorBoton: 'bg-sky-600 text-white hover:bg-sky-700',
            testId: 'tarjeta-todos-los-equipos',
            vencimiento: null,
        },
        {
            titulo: 'Equipos al día',
            valor: vencimientos.al_dia,
            icono: CheckCircle2,
            color: 'border-emerald-200 bg-emerald-100 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200',
            colorBoton: 'bg-emerald-600 text-white hover:bg-emerald-700',
            testId: 'tarjeta-al-dia',
            vencimiento: 'AL_DIA',
        },
        {
            titulo: 'Equipos próximos a vencer',
            valor: vencimientos.proximos_a_vencer,
            icono: Clock,
            color: 'border-amber-200 bg-amber-100 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
            colorBoton: 'bg-amber-500 text-white hover:bg-amber-600',
            testId: 'tarjeta-proximos-a-vencer',
            vencimiento: 'PROXIMO_A_VENCER',
        },
        {
            titulo: 'Equipos vencidos',
            valor: vencimientos.vencidos,
            icono: AlertTriangle,
            color: 'border-red-200 bg-red-100 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200',
            colorBoton: 'bg-red-600 text-white hover:bg-red-700',
            testId: 'tarjeta-vencidos',
            vencimiento: 'VENCIDO',
        },
    ];

    const tarjetasVisibles = tarjetas.filter(
        (tarjeta) => tarjeta.valor !== null,
    );

    const verEquipos = (vencimiento: string | null) => {
        setVista((actual) => ({ n: actual.n + 1, vencimiento }));
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {equipos_urgentes && options ? (
                    <EquiposUrgentes
                        equipos={equipos_urgentes}
                        options={options}
                    />
                ) : null}

                {tarjetasVisibles.length > 0 ? (
                    <div className="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {tarjetasVisibles.map((tarjeta) => (
                            <Card
                                key={tarjeta.testId}
                                data-test={tarjeta.testId}
                                className={tarjeta.color}
                            >
                                <CardHeader className="flex flex-row items-center justify-between space-y-0">
                                    <CardTitle className="text-sm font-medium">
                                        {tarjeta.titulo}
                                    </CardTitle>
                                    <tarjeta.icono className="h-5 w-5" />
                                </CardHeader>
                                <CardContent className="flex items-end justify-between gap-2">
                                    <p className="text-3xl font-bold tabular-nums">
                                        {tarjeta.valor}
                                    </p>
                                    {equipos ? (
                                        <Button
                                            type="button"
                                            className={tarjeta.colorBoton}
                                            size="sm"
                                            onClick={() =>
                                                verEquipos(tarjeta.vencimiento)
                                            }
                                            data-test={`${tarjeta.testId}-ver`}
                                        >
                                            Ver
                                        </Button>
                                    ) : null}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : null}

                {equipos && options ? (
                    <EquiposTabla
                        key={vista.n}
                        equipos={equipos}
                        options={options}
                        vencimientoInicial={vista.vencimiento}
                    />
                ) : null}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
