import { Form } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { destroy } from "@/routes/items";
import type { Item } from "@/types";

type Props = {
    item: Item | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteItemModal({ item, open, onOpenChange }: Props) {
    if (!item) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(item.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar ítem?</DialogTitle>
                                <DialogDescription>
                                    Esta acción eliminará el ítem{" "}
                                    <strong>"{item.nombre}"</strong>. Solo es
                                    posible si no se ha usado en mantenimientos.
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    type="submit"
                                    data-test="item-delete-confirm"
                                    disabled={processing}
                                >
                                    Eliminar ítem
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
