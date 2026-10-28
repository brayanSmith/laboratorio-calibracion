import { Form } from '@inertiajs/react';
import { cambiarEstado } from '@/actions/App/Modules/Equipos/Http/Controllers/EquipoController';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Equipo } from '@/types';

type Props = {
    equipo: Equipo | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Activa o inactiva el equipo (lo contrario de su estado actual), pidiendo el motivo, que
 * queda en el historial de estado del equipo.
 */
export default function CambiarEstadoEquipoModal({
    equipo,
    open,
    onOpenChange,
}: Props) {
    if (!equipo) {
        return null;
    }

    const activar = !equipo.activo;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent data-test="cambiar-estado-equipo-modal">
                <Form
                    key={`${equipo.id}-${String(open)}`}
                    {...cambiarEstado.form(equipo.id)}
                    options={{ preserveScroll: true, preserveState: true }}
                    className="space-y-4"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {activar
                                        ? '¿Activar equipo?'
                                        : '¿Inactivar equipo?'}
                                </DialogTitle>
                                <DialogDescription>
                                    El equipo <strong>"{equipo.codigo}"</strong>{' '}
                                    {activar
                                        ? 'volverá a estar activo.'
                                        : 'quedará inactivo.'}{' '}
                                    Indica el motivo: queda en su historial de
                                    estado.
                                </DialogDescription>
                            </DialogHeader>

                            {activar && equipo.motivo_inactivo ? (
                                <p className="rounded-md bg-muted px-3 py-2 text-sm">
                                    <span className="text-muted-foreground">
                                        Estaba inactivo por:
                                    </span>{' '}
                                    {equipo.motivo_inactivo}
                                </p>
                            ) : null}

                            <input
                                type="hidden"
                                name="activo"
                                value={activar ? '1' : '0'}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="motivo-estado">
                                    Motivo{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Textarea
                                    id="motivo-estado"
                                    name="motivo"
                                    rows={3}
                                    maxLength={500}
                                    required
                                    autoFocus
                                    placeholder={
                                        activar
                                            ? 'Ej. El equipo fue reparado y está operativo'
                                            : 'Ej. Equipo dado de baja por daño irreparable'
                                    }
                                    data-test="motivo-estado-equipo"
                                />
                                <InputError message={errors.motivo} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    variant={
                                        activar ? 'default' : 'destructive'
                                    }
                                    loading={processing}
                                    data-test="cambiar-estado-equipo-confirm"
                                >
                                    {activar
                                        ? 'Activar equipo'
                                        : 'Inactivar equipo'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
