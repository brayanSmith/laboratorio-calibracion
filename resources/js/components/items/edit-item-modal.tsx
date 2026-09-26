import { Form } from "@inertiajs/react";
import ItemController from "@/actions/App/Http/Controllers/ItemController";
import ItemFormFields from "@/components/items/item-form-fields";
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
import type { Item } from "@/types";

type Props = {
    item: Item | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditItemModal({ item, open, onOpenChange }: Props) {
    if (!item) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <Form
                    key={`${item.id}-${String(open)}`}
                    {...ItemController.update.form(item.id)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Editar ítem</DialogTitle>
                                <DialogDescription>
                                    Actualiza los datos del ítem
                                </DialogDescription>
                            </DialogHeader>

                            <ItemFormFields item={item} errors={errors} />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="item-update-submit"
                                >
                                    Guardar cambios
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
