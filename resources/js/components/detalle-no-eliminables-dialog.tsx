import { TriangleAlert } from 'lucide-react';
import { useEffect, useState } from 'react';
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
import type { DetalleNoEliminables } from '@/types/ui';

const EVENTO = 'ver-detalle-no-eliminables';

/**
 * Abre la ventana con el detalle desde cualquier parte (ej. el botón "Ver detalle" de un
 * aviso), sin pasar estado por los componentes: la ventana escucha este evento.
 */
export function verDetalleNoEliminables(detalle: DetalleNoEliminables): void {
    window.dispatchEvent(new CustomEvent(EVENTO, { detail: detalle }));
}

/**
 * Qué registros no se pudieron eliminar y cuáles los tienen en uso, para que el usuario sepa
 * qué revisar o eliminar primero. Va montada una sola vez, junto a los avisos.
 */
export default function DetalleNoEliminablesDialog() {
    const [detalle, setDetalle] = useState<DetalleNoEliminables | null>(null);

    useEffect(() => {
        const abrir = (evento: Event) =>
            setDetalle((evento as CustomEvent<DetalleNoEliminables>).detail);

        window.addEventListener(EVENTO, abrir);

        return () => window.removeEventListener(EVENTO, abrir);
    }, []);

    const registros = detalle?.registros ?? [];

    return (
        <Dialog
            open={detalle !== null}
            onOpenChange={(abierta) => {
                if (!abierta) {
                    setDetalle(null);
                }
            }}
        >
            <DialogContent
                className="max-h-[85vh] overflow-y-auto sm:max-w-xl"
                data-test="detalle-no-eliminables"
            >
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <TriangleAlert className="size-5 text-red-500" />
                        {registros.length === 1
                            ? 'No se pudo eliminar'
                            : `${registros.length} registros no se pudieron eliminar`}
                    </DialogTitle>
                    <DialogDescription>
                        Estos registros están en uso. Para eliminarlos, primero
                        elimina o cambia los registros que los usan.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3">
                    {registros.map((registro, indice) => (
                        <div
                            key={`${registro.nombre}-${indice}`}
                            className="space-y-2 rounded-lg border p-3"
                        >
                            <div>
                                <p className="font-medium">{registro.nombre}</p>
                                <p className="text-sm text-muted-foreground">
                                    {registro.motivo}
                                </p>
                            </div>

                            {registro.dependencias.map((dependencia) => (
                                <div key={dependencia.titulo}>
                                    <p className="text-xs font-medium text-muted-foreground">
                                        {dependencia.titulo} (
                                        {dependencia.total})
                                    </p>
                                    <div className="mt-1 flex flex-wrap gap-1.5">
                                        {dependencia.registros.map(
                                            (nombre, posicion) => (
                                                <span
                                                    key={`${nombre}-${posicion}`}
                                                    className="rounded-md border bg-muted/50 px-2 py-0.5 font-mono text-xs"
                                                >
                                                    {nombre}
                                                </span>
                                            ),
                                        )}
                                        {dependencia.total >
                                        dependencia.registros.length ? (
                                            <span className="px-1 py-0.5 text-xs text-muted-foreground">
                                                y{' '}
                                                {dependencia.total -
                                                    dependencia.registros
                                                        .length}{' '}
                                                más
                                            </span>
                                        ) : null}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ))}
                </div>

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
