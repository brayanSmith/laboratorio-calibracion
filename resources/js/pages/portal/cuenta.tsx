import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { contrasena } from '@/routes/portal/cuenta';

type Props = {
    cuenta: { usuario: string | null; correo: string };
};

/** "Mi cuenta" del portal: los datos de acceso y el cambio de contraseña. */
export default function PortalCuenta({ cuenta }: Props) {
    return (
        <>
            <Head title="Mi cuenta" />

            <div className="max-w-xl space-y-6">
                <Heading
                    title="Mi cuenta"
                    description="Tus datos de acceso al portal."
                />

                <dl className="grid gap-3 rounded-lg border bg-background p-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Usuario
                        </dt>
                        <dd className="font-medium">{cuenta.usuario}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Correo (para recuperar la contraseña)
                        </dt>
                        <dd className="font-medium">{cuenta.correo}</dd>
                    </div>
                </dl>

                <div className="space-y-4 rounded-lg border bg-background p-4">
                    <Heading
                        variant="small"
                        title="Cambiar contraseña"
                        description="Usa al menos 8 caracteres."
                    />

                    <Form
                        {...contrasena.form()}
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="contrasena_actual">
                                        Contraseña actual
                                    </Label>
                                    <PasswordInput
                                        id="contrasena_actual"
                                        name="contrasena_actual"
                                        autoComplete="current-password"
                                        required
                                    />
                                    <InputError
                                        message={errors.contrasena_actual}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="contrasena">
                                        Nueva contraseña
                                    </Label>
                                    <PasswordInput
                                        id="contrasena"
                                        name="contrasena"
                                        autoComplete="new-password"
                                        required
                                    />
                                    <InputError message={errors.contrasena} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="contrasena_confirmation">
                                        Confirmar nueva contraseña
                                    </Label>
                                    <PasswordInput
                                        id="contrasena_confirmation"
                                        name="contrasena_confirmation"
                                        autoComplete="new-password"
                                        required
                                    />
                                </div>
                                <div>
                                    <Button
                                        type="submit"
                                        loading={processing}
                                        data-test="portal-cambiar-contrasena"
                                    >
                                        Guardar contraseña
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
