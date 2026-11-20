import { Link, useHttp } from '@inertiajs/react';
import {
    ChevronDown,
    MailCheck,
    MapPin,
    PackageSearch,
    ScanBarcode,
    Send,
} from 'lucide-react';
import { useState } from 'react';
import { enviarEnlace } from '@/actions/App/Modules/Ingresos/Http/Controllers/SeguimientoBahiasController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { seguimiento } from '@/routes';

/** Un correo con forma válida (el servidor lo vuelve a validar). */
const CORREO_VALIDO = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

/**
 * Botón de la página de inicio para los clientes: despliega las dos formas de ver sus
 * equipos sin cuenta. Por ingreso lleva a la consulta con el código del correo; por bahía
 * pide su correo y le envía un enlace para ver todas las bahías que tiene asignadas.
 */
export default function ElegirSeguimiento() {
    const [porBahia, setPorBahia] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        size="lg"
                        variant="outline"
                        data-test="welcome-seguimiento"
                    >
                        <PackageSearch />
                        Ver seguimiento de equipos
                        <ChevronDown className="opacity-60" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="center" className="w-72">
                    <DropdownMenuItem
                        asChild
                        className="items-start gap-3 py-2"
                    >
                        <Link
                            href={seguimiento()}
                            data-test="welcome-seguimiento-ingreso"
                        >
                            <ScanBarcode className="mt-0.5 size-5" />
                            <span className="grid gap-0.5">
                                <span className="font-medium">
                                    Seguimiento por ingreso
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    Con el código que te llegó por correo.
                                </span>
                            </span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        className="items-start gap-3 py-2"
                        onSelect={() => setPorBahia(true)}
                        data-test="welcome-seguimiento-bahia"
                    >
                        <MapPin className="mt-0.5 size-5" />
                        <span className="grid gap-0.5">
                            <span className="font-medium">
                                Seguimiento por bahía
                            </span>
                            <span className="text-xs text-muted-foreground">
                                Todos los equipos de tus bahías, con un enlace a
                                tu correo.
                            </span>
                        </span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={porBahia} onOpenChange={setPorBahia}>
                <DialogContent className="sm:max-w-md">
                    {porBahia ? (
                        <PedirEnlace onCerrar={() => setPorBahia(false)} />
                    ) : null}
                </DialogContent>
            </Dialog>
        </>
    );
}

/** Pide el correo y envía el enlace; luego muestra el aviso de que se envió. */
function PedirEnlace({ onCerrar }: { onCerrar: () => void }) {
    const { data, setData, post, processing, errors } = useHttp<
        { correo: string },
        { mensaje: string }
    >({ correo: '' });
    const [mensaje, setMensaje] = useState<string | null>(null);
    const [demasiados, setDemasiados] = useState(false);

    const valido = CORREO_VALIDO.test(data.correo.trim());

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();

        if (!valido) {
            return;
        }

        setDemasiados(false);
        void post(enviarEnlace().url, {
            onSuccess: (respuesta) => setMensaje(respuesta.mensaje),
            onHttpException: (respuesta) => {
                if (respuesta.status === 429) {
                    setDemasiados(true);

                    return false;
                }
            },
        }).catch(() => undefined);
    };

    if (mensaje) {
        return (
            <>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <MailCheck className="size-5 text-emerald-600" />
                        Revisa tu correo
                    </DialogTitle>
                    <DialogDescription>{mensaje}</DialogDescription>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    El correo trae el botón{' '}
                    <strong>Ver ingresos por bahía</strong>. El enlace es
                    personal y vence en unos días; si vence, pide uno nuevo
                    aquí.
                </p>
                <DialogFooter>
                    <Button
                        type="button"
                        onClick={onCerrar}
                        data-test="seguimiento-bahia-listo"
                    >
                        Listo
                    </Button>
                </DialogFooter>
            </>
        );
    }

    return (
        <form onSubmit={enviar} className="grid gap-4">
            <DialogHeader>
                <DialogTitle>Seguimiento por bahía</DialogTitle>
                <DialogDescription>
                    Escribe el correo que tienes registrado con el laboratorio y
                    te enviaremos un enlace para ver los equipos de todas tus
                    bahías.
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-2">
                <Label htmlFor="correo-seguimiento">Correo electrónico</Label>
                <Input
                    id="correo-seguimiento"
                    type="email"
                    inputMode="email"
                    autoComplete="email"
                    autoFocus
                    value={data.correo}
                    onChange={(evento) =>
                        setData('correo', evento.target.value)
                    }
                    placeholder="nombre@empresa.com"
                    data-test="seguimiento-bahia-correo"
                />
                <InputError message={errors.correo} />
            </div>

            {demasiados ? (
                <Alert variant="destructive">
                    <AlertDescription>
                        Hiciste muchas solicitudes. Espera unos minutos e
                        inténtalo de nuevo.
                    </AlertDescription>
                </Alert>
            ) : null}

            <DialogFooter className="gap-2">
                <DialogClose asChild>
                    <Button type="button" variant="secondary">
                        Cancelar
                    </Button>
                </DialogClose>
                <Button
                    type="submit"
                    disabled={!valido}
                    loading={processing}
                    data-test="seguimiento-bahia-enviar"
                >
                    {processing ? null : <Send />}
                    Enviar
                </Button>
            </DialogFooter>
        </form>
    );
}
