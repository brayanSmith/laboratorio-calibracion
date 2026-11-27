import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    cliente: { nombre: string; correo: string };
    /** El mismo enlace firmado del correo: el formulario se envía a él. */
    accion: string;
};

/** Desde el enlace del correo: el cliente elige su usuario y su contraseña del portal. */
export default function PortalCompletarRegistro({ cliente, accion }: Props) {
    return (
        <>
            <Head title="Crear cuenta" />

            <div className="rounded-md bg-muted p-3 text-center text-sm">
                <p className="font-medium">{cliente.nombre}</p>
                <p className="text-muted-foreground">{cliente.correo}</p>
            </div>

            <Form
                action={accion}
                method="post"
                resetOnError={['contrasena', 'contrasena_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="portal_usuario">Usuario</Label>
                            <Input
                                id="portal_usuario"
                                name="portal_usuario"
                                required
                                autoFocus
                                autoComplete="username"
                                autoCapitalize="none"
                                placeholder="Letras, números y . _ - @"
                            />
                            <InputError message={errors.portal_usuario} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="contrasena">Contraseña</Label>
                            <PasswordInput
                                id="contrasena"
                                name="contrasena"
                                required
                                autoComplete="new-password"
                                placeholder="Al menos 8 caracteres"
                            />
                            <InputError message={errors.contrasena} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="contrasena_confirmation">
                                Confirmar contraseña
                            </Label>
                            <PasswordInput
                                id="contrasena_confirmation"
                                name="contrasena_confirmation"
                                required
                                autoComplete="new-password"
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            loading={processing}
                            data-test="portal-completar-registro-button"
                        >
                            Crear mi cuenta
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

PortalCompletarRegistro.layout = {
    title: 'Crea tu cuenta',
    description:
        'Elige el usuario y la contraseña con los que entrarás al portal.',
};
