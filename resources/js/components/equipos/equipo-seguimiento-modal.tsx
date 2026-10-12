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
import type { SeguimientoProgramacion } from '@/types';

type Props = {
    /** Descripción de la programación, ej: "Mantenimiento + Calibración". */
    titulo: string;
    seguimientos: SeguimientoProgramacion[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** Historial de solo lectura de una programación, del más reciente al más antiguo. */
export default function EquipoSeguimientoModal({
    titulo,
    seguimientos,
    open,
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Seguimiento</DialogTitle>
                    <DialogDescription>{titulo}</DialogDescription>
                </DialogHeader>

                {seguimientos.length > 0 ? (
                    <ol
                        className="space-y-4 border-l pl-4"
                        data-test="seguimiento-historial"
                    >
                        {seguimientos.map((seguimiento) => (
                            <li
                                key={seguimiento.id}
                                className="relative space-y-1"
                                data-test="seguimiento-item"
                            >
                                <span className="absolute top-1.5 -left-[1.4rem] size-2 rounded-full bg-primary" />
                                <div className="flex flex-wrap items-center gap-2 text-sm">
                                    <span className="font-medium">
                                        {faseLabel(seguimiento.fase)}
                                    </span>
                                    <Badge variant="secondary">
                                        {seguimientoLabel(
                                            seguimiento.seguimiento,
                                        )}
                                    </Badge>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {formatearFechaSeguimiento(
                                        seguimiento.created_at,
                                    )}
                                    {seguimiento.usuario
                                        ? ` · ${seguimiento.usuario.name}`
                                        : ''}
                                </p>
                                {seguimiento.observaciones ? (
                                    <p className="text-sm">
                                        {seguimiento.observaciones}
                                    </p>
                                ) : null}
                            </li>
                        ))}
                    </ol>
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
