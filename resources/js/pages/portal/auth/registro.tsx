import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes/portal';
import { store } from '@/routes/portal/registro';

/**
 * Crear cuenta del portal: el cliente escribe el correo que tiene registrado en el
 * laboratorio y le llega un enlace para elegir su usuario y su contraseña.
 */
export default function PortalRegistro() {
    return (
        <>
            <Head title="Crear cuenta" />

            <Form {...store.form()} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="correo">Correo electrónico</Label>
                            <Input
                                id="correo"
                                name="correo"
                                type="email"
                                required
                                autoFocus
                                autoComplete="email"
                                placeholder="El correo que tiene registrado el laboratorio"
                            />
                            <InputError message={errors.correo} />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            loading={processing}
                            data-test="portal-registro-button"
                        >
                            Crear cuenta
                        </Button>
                    </div>
                )}
            </Form>

            <p className="text-center text-sm text-muted-foreground">
                ¿Ya tienes cuenta?{' '}
                <TextLink href={login()}>Inicia sesión</TextLink>
            </p>
        </>
    );
}

PortalRegistro.layout = {
    title: 'Crear cuenta',
    description:
        'Escribe el correo que tienes registrado en el laboratorio. Te enviaremos un enlace para elegir tu usuario y tu contraseña.',
};
