import { Loader2 } from 'lucide-react';
import { lazy, Suspense } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const AgendaCalendario = lazy(
    () => import('@/components/agenda/agenda-calendario'),
);

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** Modal con el calendario de tareas pendientes; la librería solo se descarga al abrirla. */
export default function AgendaModal({ open, onOpenChange }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle>Agenda</DialogTitle>
                    <DialogDescription>
                        Mantenimientos, calibraciones y despachos pendientes por
                        fecha
                    </DialogDescription>
                </DialogHeader>

                {open ? (
                    <Suspense
                        fallback={
                            <div className="flex justify-center p-10">
                                <Loader2 className="size-6 animate-spin text-muted-foreground" />
                            </div>
                        }
                    >
                        <AgendaCalendario />
                    </Suspense>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
