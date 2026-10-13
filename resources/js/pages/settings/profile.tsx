import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import FirmaCanvas from '@/components/firma-canvas';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

type Props = {
    /** Ruta pública de la firma registrada, o null si todavía no hay una. */
    firmaUrl: string | null;
};

export default function Profile({ firmaUrl }: Props) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your name and email address"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div
                                className="grid gap-2"
                                data-test="firma-section"
                            >
                                <Label>Firma</Label>
                                <p className="text-xs text-muted-foreground">
                                    Aparece en los PDF de mantenimiento y
                                    calibración que firmes como técnico.
                                </p>

                                {firmaUrl ? (
                                    <img
                                        src={firmaUrl}
                                        alt="Tu firma actual"
                                        className="h-20 w-fit rounded-md border bg-white object-contain p-1"
                                        data-test="firma-actual"
                                    />
                                ) : null}

                                <Tabs defaultValue="dibujar">
                                    <TabsList>
                                        <TabsTrigger value="dibujar">
                                            Dibujar firma
                                        </TabsTrigger>
                                        <TabsTrigger value="subir">
                                            Subir imagen
                                        </TabsTrigger>
                                    </TabsList>

                                    <TabsContent value="dibujar">
                                        <FirmaCanvas name="firma" />
                                    </TabsContent>

                                    <TabsContent
                                        value="subir"
                                        className="space-y-2"
                                    >
                                        <Input
                                            id="firma"
                                            name="firma"
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Imagen PNG, JPG o WebP de máximo 2
                                            MB.
                                            {firmaUrl
                                                ? ' Si eliges una nueva, reemplaza a la actual.'
                                                : ''}
                                        </p>
                                    </TabsContent>
                                </Tabs>
                                <InputError message={errors.firma} />

                                {firmaUrl ? (
                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            id="eliminar_firma"
                                            name="eliminar_firma"
                                        />
                                        <Label htmlFor="eliminar_firma">
                                            Quitar firma actual
                                        </Label>
                                    </div>
                                ) : null}
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
