import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes/portal';
import { store } from '@/routes/portal/olvide';

/** Recuperar el acceso al portal: se envía una contraseña nueva al correo del cliente. */
export default function PortalOlvide() {
    return (
        <>
            <Head title="Recuperar contraseña" />

            <Form {...store.form()} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="usuario_o_correo">
                                Usuario o correo electrónico
                            </Label>
                            <Input
                                id="usuario_o_correo"
                                name="usuario_o_correo"
                                required
                                autoFocus
                                autoCapitalize="none"
                                placeholder="Tu usuario o tu correo"
                            />
                            <InputError message={errors.usuario_o_correo} />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            loading={processing}
                            data-test="portal-olvide-button"
                        >
                            Enviarme una contraseña nueva
                        </Button>
                    </div>
                )}
            </Form>

            <p className="text-center text-sm text-muted-foreground">
                ¿La recordaste?{' '}
                <TextLink href={login()}>Volver a entrar</TextLink>
            </p>
        </>
    );
}

PortalOlvide.layout = {
    title: 'Recuperar contraseña',
    description:
        'Te enviaremos una contraseña nueva al correo que tiene registrado el laboratorio.',
};
