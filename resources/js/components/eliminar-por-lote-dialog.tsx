import { router } from '@inertiajs/react';
import { useState } from 'react';
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

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Ruta que elimina los registros (ver EliminaPorLote en el backend). */
    url: string;
    ids: number[];
    /** Nombre en plural de los registros, ej. "ítems". */
    nombre: string;
    onEliminados: () => void;
};

/**
 * Confirma y envía la eliminación por lote de las filas elegidas en una tabla. El servidor
 * aplica a cada registro las mismas reglas que al eliminarlo solo, salta los que no se
 * pueden eliminar y responde con un aviso de cuántos se eliminaron y por qué no el resto.
 */
export default function EliminarPorLoteDialog({
    open,
    onOpenChange,
    url,
    ids,
    nombre,
    onEliminados,
}: Props) {
    const [eliminando, setEliminando] = useState(false);

    const eliminar = () => {
        router.post(
            url,
            { ids },
            {
                preserveScroll: true,
                onStart: () => setEliminando(true),
                onSuccess: () => {
                    onEliminados();
                    onOpenChange(false);
                },
                onFinish: () => setEliminando(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent data-test="eliminar-lote-dialog">
                <DialogHeader>
                    <DialogTitle>
                        ¿Eliminar {ids.length} {nombre}?
                    </DialogTitle>
                    <DialogDescription>
                        Se eliminarán todos los registros seleccionados. Los que
                        no se puedan eliminar (por ejemplo, porque ya están en
                        uso) se saltan y te avisamos por qué. Esta acción no se
                        puede deshacer desde esta pantalla.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={eliminando}
                        >
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        loading={eliminando}
                        onClick={eliminar}
                        data-test="eliminar-lote-confirmar"
                    >
                        Eliminar {ids.length}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
