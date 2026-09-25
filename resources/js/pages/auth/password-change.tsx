import { Form, Head, Link } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { update } from '@/routes/password-change';

type Props = {
    passwordRules: string;
};

export default function PasswordChange({ passwordRules }: Props) {
    return (
        <>
            <Head title="Cambiar contraseña" />

            <Alert>
                <KeyRound />
                <AlertTitle>Debes cambiar tu contraseña</AlertTitle>
                <AlertDescription>
                    Iniciaste sesión con una contraseña temporal. Define una
                    nueva para continuar.
                </AlertDescription>
            </Alert>

            <Form
                {...update.form()}
                resetOnSuccess={[
                    'current_password',
                    'password',
                    'password_confirmation',
                ]}
            >
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="current_password">
                                Contraseña temporal
                            </Label>
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                autoComplete="current-password"
                                placeholder="Contraseña temporal"
                                autoFocus
                            />
                            <InputError message={errors.current_password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Nueva contraseña</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                placeholder="Nueva contraseña"
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirmar nueva contraseña
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                placeholder="Confirmar nueva contraseña"
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            className="w-full"
                            disabled={processing}
                            data-test="password-change-button"
                        >
                            {processing && <Spinner />}
                            Cambiar contraseña
                        </Button>

                        <div className="text-center">
                            <Link
                                href={logout()}
                                as="button"
                                className="text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
                                data-test="password-change-logout"
                            >
                                Cerrar sesión
                            </Link>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

PasswordChange.layout = {
    title: 'Cambia tu contraseña',
    description: 'Elige una contraseña que solo tú conozcas',
};
