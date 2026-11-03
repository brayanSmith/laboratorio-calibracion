import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/** Filtro de columna con el que la tarjeta abre la tabla (id de la columna y su valor). */
export type FiltroTarjeta = { id: string; value: unknown };

/** Tonos de las tarjetas, los mismos de las tarjetas del Dashboard. */
const TONOS = {
    sky: {
        tarjeta:
            'border-sky-200 bg-sky-100 text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200',
        boton: 'bg-sky-600 text-white hover:bg-sky-700',
        anillo: 'ring-sky-500',
    },
    amber: {
        tarjeta:
            'border-amber-200 bg-amber-100 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
        boton: 'bg-amber-500 text-white hover:bg-amber-600',
        anillo: 'ring-amber-500',
    },
    blue: {
        tarjeta:
            'border-blue-200 bg-blue-100 text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200',
        boton: 'bg-blue-600 text-white hover:bg-blue-700',
        anillo: 'ring-blue-500',
    },
    emerald: {
        tarjeta:
            'border-emerald-200 bg-emerald-100 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200',
        boton: 'bg-emerald-600 text-white hover:bg-emerald-700',
        anillo: 'ring-emerald-500',
    },
    red: {
        tarjeta:
            'border-red-200 bg-red-100 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200',
        boton: 'bg-red-600 text-white hover:bg-red-700',
        anillo: 'ring-red-500',
    },
} as const;

export type TarjetaEstado = {
    /** Identificador de la tarjeta (también su data-test). */
    id: string;
    titulo: string;
    valor: number;
    icono: LucideIcon;
    tono: keyof typeof TONOS;
    /** Texto pequeño debajo del número (por ejemplo, un desglose). */
    detalle?: string;
    /** Filtros que aplica "Ver"; vacío muestra todos los registros. */
    filtros: FiltroTarjeta[];
};

/**
 * Tabla filtrada desde las tarjetas: cada "Ver" vuelve a montar la tabla (con `key`) ya
 * filtrada, sin pedir nada al servidor, como las tarjetas del Dashboard.
 */
export function useFiltroPorTarjeta() {
    const [vista, setVista] = useState<{
        n: number;
        tarjeta: string | null;
        filtros: FiltroTarjeta[];
    }>({ n: 0, tarjeta: null, filtros: [] });

    return {
        claveTabla: vista.n,
        filtrosIniciales: vista.filtros,
        tarjetaActiva: vista.tarjeta,
        ver: (tarjeta: TarjetaEstado) =>
            setVista((actual) => ({
                n: actual.n + 1,
                tarjeta: tarjeta.id,
                filtros: tarjeta.filtros,
            })),
    };
}

/**
 * Fila de tarjetas con el conteo de cada estado de un listado. "Ver" filtra la tabla por
 * ese estado y la tarjeta elegida queda resaltada.
 */
export default function TarjetasEstado({
    tarjetas,
    activa,
    onVer,
}: {
    tarjetas: TarjetaEstado[];
    activa: string | null;
    onVer: (tarjeta: TarjetaEstado) => void;
}) {
    return (
        <div
            className={cn(
                'grid auto-rows-min gap-4 md:grid-cols-2',
                tarjetas.length >= 5 ? 'xl:grid-cols-5' : 'xl:grid-cols-4',
            )}
        >
            {tarjetas.map((tarjeta) => {
                const tono = TONOS[tarjeta.tono];

                return (
                    <Card
                        key={tarjeta.id}
                        data-test={`tarjeta-${tarjeta.id}`}
                        className={cn(
                            tono.tarjeta,
                            activa === tarjeta.id &&
                                cn('ring-2 ring-offset-2', tono.anillo),
                        )}
                    >
                        <CardHeader className="flex flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-sm font-medium">
                                {tarjeta.titulo}
                            </CardTitle>
                            <tarjeta.icono className="h-5 w-5" />
                        </CardHeader>
                        <CardContent className="flex items-end justify-between gap-2">
                            <div>
                                <p className="text-3xl font-bold tabular-nums">
                                    {tarjeta.valor}
                                </p>
                                {tarjeta.detalle ? (
                                    <p className="text-xs opacity-80">
                                        {tarjeta.detalle}
                                    </p>
                                ) : null}
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                className={tono.boton}
                                onClick={() => onVer(tarjeta)}
                                data-test={`tarjeta-${tarjeta.id}-ver`}
                            >
                                Ver
                            </Button>
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}
