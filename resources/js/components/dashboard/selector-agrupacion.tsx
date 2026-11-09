import { useState } from 'react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    AGRUPACIONES,
    agrupacionDisponible,
    agrupacionSugerida,
} from '@/lib/periodos-grafico';
import type { Agrupacion } from '@/lib/periodos-grafico';

/**
 * Agrupación del eje de tiempo de un gráfico. Mientras el usuario no elija se ajusta al
 * largo del periodo; si el periodo cambia y la elegida ya no tiene sentido (meses en una
 * semana), vuelve a la sugerida.
 */
export function useAgrupacion(desde: string, hasta: string) {
    const [elegida, setElegida] = useState<Agrupacion | null>(null);
    const agrupacion =
        elegida !== null && agrupacionDisponible(elegida, desde, hasta)
            ? elegida
            : agrupacionSugerida(desde, hasta);

    return { agrupacion, elegir: setElegida };
}

/**
 * Botones para agrupar por días, semanas, meses, trimestres, semestres o años; los más
 * largos que el periodo quedan deshabilitados.
 */
export default function SelectorAgrupacion({
    agrupacion,
    onChange,
    desde,
    hasta,
}: {
    agrupacion: Agrupacion;
    onChange: (agrupacion: Agrupacion) => void;
    desde: string;
    hasta: string;
}) {
    return (
        <ToggleGroup
            type="single"
            variant="outline"
            size="sm"
            value={agrupacion}
            onValueChange={(valor) => {
                if (valor) {
                    onChange(valor as Agrupacion);
                }
            }}
            aria-label="Agrupar por"
        >
            {AGRUPACIONES.map((opcion) => (
                <ToggleGroupItem
                    key={opcion.value}
                    value={opcion.value}
                    disabled={!agrupacionDisponible(opcion.value, desde, hasta)}
                    className="px-2.5 text-xs"
                    data-test={`agrupar-${opcion.value}`}
                >
                    {opcion.label}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
