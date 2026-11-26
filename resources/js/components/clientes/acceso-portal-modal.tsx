import { Form } from '@inertiajs/react';
import { Dices } from 'lucide-react';
import { useState } from 'react';
import { update } from '@/actions/App/Modules/Catalogos/Http/Controllers/ClientePortalController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { formatearFecha } from '@/lib/fechas';
import { login } from '@/routes/portal';
import type { Cliente } from '@/types';

type Props = {
    cliente: Cliente | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** Contraseña al azar fácil de dictar: sin caracteres que se confunden (0/O, 1/l). */
function generarContrasena(): string {
    const caracteres =
        'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    return Array.from(crypto.getRandomValues(new Uint8Array(10)))
        .map((valor) => caracteres[valor % caracteres.length])
        .join('');
}

/**
 * El acceso del cliente al portal de clientes: si está habilitado, su usuario y una
 * contraseña nueva (escrita o generada), que se le puede enviar a su correo.
 */
export default function AccesoPortalModal({
    cliente,
    open,
    onOpenChange,
}: Props) {
    const [activo, setActivo] = useState(cliente?.portal.activo ?? false);
    const [contrasena, setContrasena] = useState('');
    const [enviarCorreo, setEnviarCorreo] = useState(false);

    const cambiarAbierta = (abierta: boolean) => {
        if (abierta && cliente) {
            setActivo(cliente.portal.activo);
        }

        setContrasena('');
        setEnviarCorreo(false);
        onOpenChange(abierta);
    };

    if (!cliente) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={cambiarAbierta}>
            <DialogContent
                className="sm:max-w-lg"
                data-test="acceso-portal-modal"
            >
                <Form
                    key={`${cliente.id}-${String(open)}`}
                    {...update.form(cliente.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => cambiarAbierta(false)}
                    className="space-y-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Acceso al portal</DialogTitle>
                                <DialogDescription>
                                    {cliente.nombre} podrá entrar en{' '}
                                    <span className="font-medium">
                                        {login().url}
                                    </span>{' '}
                                    para ver sus equipos, los de sus bahías y
                                    sus certificados e informes.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
                                <div>
                                    <Label htmlFor="portal_activo">
                                        Acceso habilitado
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        {cliente.portal.ultimo_acceso
                                            ? `Último acceso: ${formatearFecha(cliente.portal.ultimo_acceso.slice(0, 10))}`
                                            : 'Todavía no ha entrado al portal.'}
                                    </p>
                                </div>
                                <Switch
                                    id="portal_activo"
                                    checked={activo}
                                    onCheckedChange={setActivo}
                                    data-test="portal-activo"
                                />
                                <input
                                    type="hidden"
                                    name="portal_activo"
                                    value={activo ? '1' : '0'}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="portal_usuario">Usuario</Label>
                                <Input
                                    id="portal_usuario"
                                    name="portal_usuario"
                                    defaultValue={
                                        cliente.portal.usuario ??
                                        cliente.email.toLowerCase()
                                    }
                                    autoCapitalize="none"
                                    placeholder="Ej. acme o compras@acme.com"
                                    data-test="portal-usuario"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Letras, números y . _ - @, sin espacios.
                                    Debe ser único.
                                </p>
                                <InputError message={errors.portal_usuario} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="contrasena">
                                    {cliente.portal.tiene_contrasena
                                        ? 'Nueva contraseña'
                                        : 'Contraseña'}
                                </Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="contrasena"
                                        name="contrasena"
                                        value={contrasena}
                                        onChange={(evento) =>
                                            setContrasena(evento.target.value)
                                        }
                                        autoComplete="new-password"
                                        placeholder={
                                            cliente.portal.tiene_contrasena
                                                ? 'Déjala vacía para no cambiarla'
                                                : 'Mínimo 8 caracteres'
                                        }
                                        className="font-mono"
                                        data-test="portal-contrasena"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setContrasena(generarContrasena());
                                            setEnviarCorreo(true);
                                        }}
                                        data-test="portal-generar-contrasena"
                                    >
                                        <Dices className="size-4" />
                                        Generar
                                    </Button>
                                </div>
                                <InputError message={errors.contrasena} />
                            </div>

                            <div className="flex items-start gap-2">
                                <Checkbox
                                    id="enviar_correo"
                                    checked={enviarCorreo}
                                    onCheckedChange={(marcado) =>
                                        setEnviarCorreo(marcado === true)
                                    }
                                    disabled={contrasena === ''}
                                    data-test="portal-enviar-correo"
                                />
                                <Label
                                    htmlFor="enviar_correo"
                                    className="leading-snug font-normal"
                                >
                                    Enviar el usuario y la contraseña a{' '}
                                    <span className="font-medium">
                                        {cliente.email}
                                    </span>
                                </Label>
                                <input
                                    type="hidden"
                                    name="enviar_correo"
                                    value={enviarCorreo ? '1' : '0'}
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    loading={processing}
                                    data-test="portal-guardar"
                                >
                                    Guardar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
