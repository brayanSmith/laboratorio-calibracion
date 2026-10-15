import { Form, Head } from '@inertiajs/react';
import AjustePlataformaController from '@/actions/App/Http/Controllers/Plataforma/AjustePlataformaController';
import LogoField from '@/components/empresa/logo-field';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    ajuste: {
        nombre: string | null;
        logo_url: string | null;
    };
};

export default function AjustesPlataforma({ ajuste }: Props) {
    return (
        <>
            <Head title="Ajustes de la plataforma" />

            <h1 className="sr-only">Ajustes de la plataforma</h1>

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Ajustes de la plataforma"
                    description="Nombre y logo que se muestran en la página de bienvenida"
                />

                <Form
                    {...AjustePlataformaController.update.form()}
                    className="space-y-6"
                    encType="multipart/form-data"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="nombre">Nombre</Label>
                                <Input
                                    id="nombre"
                                    name="nombre"
                                    defaultValue={ajuste.nombre ?? ''}
                                    placeholder="Si lo dejas vacío se usa el nombre de la aplicación"
                                    maxLength={255}
                                />
                                <InputError message={errors.nombre} />
                            </div>

                            <LogoField
                                empresa={{
                                    nombre: ajuste.nombre ?? 'la plataforma',
                                    logo_url: ajuste.logo_url,
                                }}
                                error={errors.logo}
                            />

                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="ajustes-plataforma-submit"
                            >
                                Guardar cambios
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
