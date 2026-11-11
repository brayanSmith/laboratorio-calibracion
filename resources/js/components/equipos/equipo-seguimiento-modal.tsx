import {
    Gauge,
    MapPin,
    PackageCheck,
    Route,
    Truck,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { LineaDeTiempo, Paso } from '@/components/linea-de-tiempo';
import type { TonoPaso } from '@/components/linea-de-tiempo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    faseLabel,
    formatearFechaSeguimiento,
    seguimientoLabel,
} from '@/lib/seguimiento-programacion';
import type { FaseSeguimiento, SeguimientoProgramacion } from '@/types';

type Props = {
    /** Descripción de la programación, ej: "Mantenimiento + Calibración". */
    titulo: string;
    seguimientos: SeguimientoProgramacion[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** Ícono de cada fase por la que pasa el equipo. */
const ICONO_FASE: Record<FaseSeguimiento, LucideIcon> = {
    BAHIA: MapPin,
    INGRESO: PackageCheck,
    MANTENIMIENTO: Wrench,
    CALIBRACION: Gauge,
    DESPACHO: Truck,
};

/** Estados que cierran bien la fase (verde) o mal (rojo); los demás van en curso (ámbar). */
const ESTADOS_BUENOS = [
    'RECIBIDO',
    'FINALIZADO',
    'ENTREGADO',
    'AUTORIZADO',
    'APROBADO',
];
const ESTADOS_MALOS = [
    'CANCELADO',
    'FUERA_DE_SERVICIO',
    'RECHAZADO',
    'DEVOLVER_MANTENIMIENTO',
];

function tonoDelEstado(seguimiento: string | null): TonoPaso {
    if (!seguimiento) {
        return 'neutro';
    }

    if (ESTADOS_BUENOS.includes(seguimiento)) {
        return 'bueno';
    }

    return ESTADOS_MALOS.includes(seguimiento) ? 'malo' : 'aviso';
}

const CLASE_BADGE: Record<TonoPaso, string> = {
    bueno: '!border-transparent !bg-emerald-500 !text-white',
    malo: '!border-transparent !bg-red-500 !text-white',
    aviso: '!border-transparent !bg-amber-500 !text-white',
    neutro: '',
};

/**
 * Traza de una programación del equipo: cada cambio de fase y de estado, del más antiguo
 * al más reciente, como una línea de tiempo (con quién y cuándo), igual que la traza de
 * los equipos no recibidos del dashboard.
 */
export default function EquipoSeguimientoModal({
    titulo,
    seguimientos,
    open,
    onOpenChange,
}: Props) {
    // Llegan del más reciente al más antiguo: la línea de tiempo va en el orden en que pasó.
    const enOrden = [...seguimientos].sort((a, b) => a.id - b.id);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[85dvh] overflow-y-auto sm:max-w-xl"
                data-test="seguimiento-modal"
            >
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Route className="h-5 w-5 text-muted-foreground" />
                        Traza de la programación
                    </DialogTitle>
                    <DialogDescription>{titulo}</DialogDescription>
                </DialogHeader>

                {enOrden.length > 0 ? (
                    <LineaDeTiempo testId="seguimiento-historial">
                        {enOrden.map((seguimiento, indice) => {
                            const tono = tonoDelEstado(seguimiento.seguimiento);
                            const esElActual = indice === enOrden.length - 1;

                            return (
                                <Paso
                                    key={seguimiento.id}
                                    icono={ICONO_FASE[seguimiento.fase] ?? Route}
                                    tono={tono}
                                    testId="seguimiento-item"
                                    titulo={
                                        <span className="flex flex-wrap items-center gap-2">
                                            {faseLabel(seguimiento.fase)}
                                            <Badge
                                                variant={
                                                    tono === 'neutro'
                                                        ? 'secondary'
                                                        : 'default'
                                                }
                                                className={CLASE_BADGE[tono]}
                                            >
                                                {seguimientoLabel(
                                                    seguimiento.seguimiento,
                                                )}
                                            </Badge>
                                            {esElActual ? (
                                                <span className="text-xs font-normal text-muted-foreground">
                                                    (estado actual)
                                                </span>
                                            ) : null}
                                        </span>
                                    }
                                >
                                    <p className="text-xs">
                                        {formatearFechaSeguimiento(
                                            seguimiento.created_at,
                                        )}
                                        {seguimiento.usuario
                                            ? ` · ${seguimiento.usuario.name}`
                                            : ' · Sistema'}
                                    </p>
                                    {seguimiento.observaciones ? (
                                        <p className="text-foreground">
                                            {seguimiento.observaciones}
                                        </p>
                                    ) : null}
                                </Paso>
                            );
                        })}
                    </LineaDeTiempo>
                ) : (
                    <p className="text-center text-sm text-muted-foreground">
                        Esta programación aún no tiene seguimiento.
                    </p>
                )}

                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Cerrar
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
