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
    total_equipos: number;
    vencimientos: {
        al_dia: number;
        proximos_a_vencer: number;
        vencidos: number;
    };
    /** Solo llegan si la persona puede ver equipos. */
    equipos_urgentes?: EquipoUrgente[];
    equipos?: Equipo[];
    options?: EquipoFormOptions;
};

type TarjetaVencimiento = {
    titulo: string;
    valor: number;
    icono: LucideIcon;
    color: string;
    /** Botón en un tono más oscuro que el de la tarjeta. */
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
            color: 'border-sky-700 bg-sky-600 text-white',
            colorBoton: 'bg-sky-800 text-white hover:bg-sky-900',
            testId: 'tarjeta-todos-los-equipos',
            vencimiento: null,
        },
        {
            titulo: 'Equipos al día',
            valor: vencimientos.al_dia,
            icono: CheckCircle2,
            color: 'border-emerald-600 bg-emerald-500 text-white',
            colorBoton: 'bg-emerald-700 text-white hover:bg-emerald-800',
            testId: 'tarjeta-al-dia',
            vencimiento: 'AL_DIA',
        },
        {
            titulo: 'Equipos próximos a vencer',
            valor: vencimientos.proximos_a_vencer,
            icono: Clock,
            color: 'border-amber-500 bg-amber-400 text-amber-950',
            colorBoton: 'bg-amber-600 text-white hover:bg-amber-700',
            testId: 'tarjeta-proximos-a-vencer',
            vencimiento: 'PROXIMO_A_VENCER',
        },
        {
            titulo: 'Equipos vencidos',
            valor: vencimientos.vencidos,
            icono: AlertTriangle,
            color: 'border-red-700 bg-red-500 text-white',
            colorBoton: 'bg-red-800 text-white hover:bg-red-900',
            testId: 'tarjeta-vencidos',
            vencimiento: 'VENCIDO',
        },
    ];

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

                <div className="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {tarjetas.map((tarjeta) => (
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
