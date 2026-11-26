import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { olvide } from '@/routes/portal';
import { store } from '@/routes/portal/login';

type Props = {
    status?: string | null;
};

/** Inicio de sesión del portal de clientes, con el usuario que les dio el laboratorio. */
export default function PortalLogin({ status }: Props) {
    return (
        <>
            <Head title="Portal de clientes" />

            {status ? (
                <div
                    className="mb-4 rounded-md bg-emerald-50 p-3 text-center text-sm font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400"
                    data-test="portal-login-status"
                >
                    {status}
                </div>
            ) : null}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
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
                                placeholder="Tu usuario"
                            />
                            <InputError message={errors.portal_usuario} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center">
                                <Label htmlFor="password">Contraseña</Label>
                                <TextLink
                                    href={olvide()}
                                    className="ml-auto text-sm"
                                >
                                    ¿Olvidaste tu contraseña?
                                </TextLink>
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="current-password"
                                placeholder="Contraseña"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center space-x-3">
                            <Checkbox id="remember" name="remember" />
                            <Label htmlFor="remember">Recordarme</Label>
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            loading={processing}
                            data-test="portal-login-button"
                        >
                            Entrar
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

PortalLogin.layout = {
    title: 'Portal de clientes',
    description:
        'Ingresa con el usuario y la contraseña que te dio el laboratorio para ver tus equipos.',
};
