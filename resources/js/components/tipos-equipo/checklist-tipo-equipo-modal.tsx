import TipoEquipoChecklistManager from '@/components/tipos-equipo/tipo-equipo-checklist-manager';
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
import type { TipoEquipo } from '@/types';

type Props = {
    tipoEquipo: TipoEquipo | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function ChecklistTipoEquipoModal({
    tipoEquipo,
    open,
    onOpenChange,
}: Props) {
    if (!tipoEquipo) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        Checklist de mantenimiento: {tipoEquipo.nombre}
                    </DialogTitle>
                    <DialogDescription>
                        Ítems que se verifican al hacer mantenimiento a los
                        equipos de este tipo.
                    </DialogDescription>
                </DialogHeader>

                <TipoEquipoChecklistManager tipoEquipo={tipoEquipo} />

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary">Cerrar</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
